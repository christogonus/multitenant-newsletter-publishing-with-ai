<?php

use App\Actions\RecordPurchase;
use App\Actions\RedeemLicenseCode;
use App\Enums\Platform;
use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Integrations\Drivers\GhostDriver;
use App\Integrations\OutboundUrlGuard;
use App\Integrations\PlatformException;
use App\Models\Domain;
use App\Models\Newsletter;
use App\Models\PlatformConnection;
use App\Models\Purchase;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDnsVerifier;

afterEach(fn () => OutboundUrlGuard::resolveUsing(null));

test('typing an existing reader\'s email reveals none of their links', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create();
    $victim = Subscriber::factory()->for($newsletter)->create(['email' => 'victim@example.com']);

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'victim@example.com'])->assertRedirect();

    $this->get(publicationUrl($newsletter, 'thanks'))
        ->assertOk()
        ->assertDontSee($victim->referral_code)
        ->assertDontSee('referral progress');
});

test('a lead page signup for an existing reader does not reveal their referral link', function () {
    $newsletter = Newsletter::factory()->for(buyer(Product::Oto3Growth))->create();
    $leadPage = $newsletter->leadPages()->create(['slug' => 'gift', 'headline' => 'Gift', 'magnet_title' => 'Gift']);
    $victim = Subscriber::factory()->for($newsletter)->create(['email' => 'victim@example.com']);

    $response = $this->post(publicationUrl($newsletter, 'join/gift'), ['email' => 'victim@example.com']);

    $this->get($response->headers->get('Location'))->assertOk()->assertDontSee($victim->referral_code);
});

test('the public form cannot re-subscribe a reader who opted out', function () {
    $newsletter = Newsletter::factory()->create();
    $reader = Subscriber::factory()->for($newsletter)->create(['email' => 'left@example.com', 'unsubscribed_at' => now()->subDay(), 'consent_ip' => '198.51.100.1']);

    $this->post(publicationUrl($newsletter, 'subscribe'), ['email' => 'left@example.com'], ['REMOTE_ADDR' => '203.0.113.66']);

    $reader->refresh();
    expect($reader->unsubscribed_at)->not->toBeNull()->and($reader->consent_ip)->toBe('198.51.100.1');
});

test('a purchase on an account made with someone else\'s reseller code sends the set-password link to the inbox owner', function () {
    Notification::fake();
    $reseller = buyer(Product::Oto12Reseller);
    $reseller->licenseCodes()->create(['code' => 'NPAI-AAAA-BBBB-CCCC']);
    app(RedeemLicenseCode::class)->handle('NPAI-AAAA-BBBB-CCCC', 'Squatter', 'victim@example.com', 'attacker-pass-1');
    $account = User::where('email', 'victim@example.com')->sole();

    app(RecordPurchase::class)->handle('victim@example.com', 'Victim', Product::FrontEnd, PurchaseAction::Sale, 'TXN-V1');

    Notification::assertSentTo($account, WelcomeBuyer::class);
});

test('an existing buyer row is reused instead of failing the purchase', function () {
    $existing = User::factory()->create(['email' => 'buyer@example.com']);

    $result = app(RecordPurchase::class)->handle('BUYER@example.com', 'Buyer', Product::BumpVault, PurchaseAction::Sale, 'TXN-B1');

    expect($result['duplicate'])->toBeFalse()
        ->and($result['purchase']->user_id)->toBe($existing->id)
        ->and(Purchase::count())->toBe(1);
});

test('codes from a refunded reseller stop working', function () {
    $reseller = buyer(Product::Oto12Reseller);
    $reseller->licenseCodes()->create(['code' => 'NPAI-AAAA-BBBB-CCCC']);
    $sale = $reseller->purchases()->where('product_key', Product::Oto12Reseller)->sole();
    app(RecordPurchase::class)->handle($reseller->email, null, Product::Oto12Reseller, PurchaseAction::Refund, $sale->transaction_id);

    expect(fn () => app(RedeemLicenseCode::class)->handle('NPAI-AAAA-BBBB-CCCC', 'X', 'x@example.com', 'secret-pass-1'))
        ->toThrow(ValidationException::class);
});

test('a ghost address that points at a private network is refused', function (string $siteUrl, array $resolved) {
    Http::preventStrayRequests();
    OutboundUrlGuard::resolveUsing(fn () => $resolved);
    $newsletter = Newsletter::factory()->create();
    $connection = new PlatformConnection([
        'user_id' => $newsletter->user_id,
        'newsletter_id' => $newsletter->id,
        'platform' => Platform::Ghost,
        'credentials' => ['site_url' => $siteUrl, 'admin_key' => 'id1:'.bin2hex('secret')],
    ]);

    expect(fn () => (new GhostDriver)->test($connection))->toThrow(PlatformException::class);
    Http::assertNothingSent();
})->with([
    'private ip' => ['https://10.0.0.5', []],
    'loopback name' => ['https://internal.example', ['127.0.0.1']],
    'metadata service' => ['https://evil.example', ['169.254.169.254']],
    'plain http' => ['http://blog.example.com', ['93.184.216.34']],
]);

test('platform requests do not follow redirects', function () {
    OutboundUrlGuard::resolveUsing(fn () => ['93.184.216.34']);
    Http::fake(['blog.example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest'])]);
    $newsletter = Newsletter::factory()->create();
    $connection = new PlatformConnection([
        'user_id' => $newsletter->user_id,
        'newsletter_id' => $newsletter->id,
        'platform' => Platform::Ghost,
        'credentials' => ['site_url' => 'https://blog.example.com', 'admin_key' => 'id1:'.bin2hex('secret')],
    ]);

    expect(fn () => (new GhostDriver)->test($connection))->toThrow(PlatformException::class);
    Http::assertSentCount(1);
});

test('every csv cell is protected from spreadsheet formulas', function () {
    $newsletter = Newsletter::factory()->create();
    Subscriber::factory()->for($newsletter)->create(['email' => '=cmd@example.com']);

    $csv = $this->actingAs($newsletter->user)->get(route('subscribers.export'))->streamedContent();

    expect($csv)->toContain("'=cmd@example.com");
});

test('an unverified claim cannot block the real owner of a domain', function () {
    $squatter = Newsletter::factory()->create();
    $owner = Newsletter::factory()->create();
    $squat = Domain::create(['newsletter_id' => $squatter->id, 'hostname' => 'news.owner.com', 'verification_token' => 'squat']);
    $real = Domain::create(['newsletter_id' => $owner->id, 'hostname' => 'news.owner.com', 'verification_token' => 'real']);

    $verifier = new FakeDnsVerifier(
        txt: ['_newspub.news.owner.com' => ['newspub-verify=real']],
        cname: ['news.owner.com' => [config('newspub.public_root')]],
    );

    expect($verifier->verify($real))->toBeTrue()
        ->and(Domain::find($squat->id))->toBeNull()
        ->and($verifier->verify(Domain::create(['newsletter_id' => $squatter->id, 'hostname' => 'news.owner.com', 'verification_token' => 'real'])))->toBeFalse();
});

test('public pages are never stored by shared caches', function () {
    $newsletter = Newsletter::factory()->create();

    expect($this->get(publicationUrl($newsletter))->headers->get('Cache-Control'))->toContain('private');
});
