<?php

use App\Actions\RedeemLicenseCode;
use App\Enums\ContractStatus;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Enums\PurchaseSource;
use App\Enums\SwapStatus;
use App\Livewire\Admin\Reports;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Agency\Index as Agency;
use App\Livewire\Kits\Index as Kits;
use App\Livewire\Network\Index as Network;
use App\Livewire\Onboarding\CreatePublication;
use App\Livewire\Reseller\Index as Reseller;
use App\Models\AiUsage;
use App\Models\Client;
use App\Models\Issue;
use App\Models\Kit;
use App\Models\LicenseCode;
use App\Models\NetworkListing;
use App\Models\NetworkSwap;
use App\Models\Newsletter;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\AiSpendAlert;
use App\Support\Blocks;
use App\Support\Entitlements;
use Database\Seeders\KitSeeder;
use Database\Seeders\NetworkSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function makeKit(array $overrides = []): Kit
{
    return Kit::create(array_merge([
        'key' => 'money-basics',
        'name' => 'Money Basics',
        'niche' => 'personal finance',
        'tagline' => 'Money made simple',
        'audience' => 'Beginners',
        'voice' => 'Friendly',
        'frequency' => 'weekly',
        'topics' => ['Budgeting 101', 'Emergency funds'],
        'seed_issues' => [
            ['title' => 'Welcome', 'subject_line' => 'Welcome', 'preheader' => 'Hi', 'blocks' => [['type' => 'paragraph', 'text' => 'Hello']]],
            ['title' => 'Budgeting 101', 'blocks' => [['type' => 'heading', 'text' => 'Start here']]],
        ],
    ], $overrides));
}

test('the front end allows one publication, done-for-you five, multi unlimited', function () {
    $entitlements = app(Entitlements::class);

    expect($entitlements->publicationLimit(buyer()))->toBe(1)
        ->and($entitlements->publicationLimit(buyer(Product::Oto5Dfy)))->toBe(5)
        ->and($entitlements->publicationLimit(buyer(Product::Oto9Multi)))->toBeNull();

    $user = buyer();
    Newsletter::factory()->for($user)->create();
    expect($entitlements->canCreatePublication($user))->toBeFalse();
});

test('the start wizard sends a buyer at the limit away', function () {
    $user = buyer();
    Newsletter::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(CreatePublication::class)->assertRedirect(route('publications.index'));
});

test('installing a kit creates a publication with its topics and draft seed issues', function () {
    $kit = makeKit();
    $user = buyer(Product::Oto5Dfy);
    $this->actingAs($user);

    Livewire::test(Kits::class)->call('install', $kit->id)->assertRedirect(route('dashboard'));

    $newsletter = Newsletter::sole();
    expect($newsletter->kit_key)->toBe('money-basics')
        ->and($newsletter->topic_pipeline)->toBe(['Budgeting 101', 'Emergency funds'])
        ->and($newsletter->issues()->where('status', IssueStatus::Draft)->count())->toBe(2)
        ->and($user->fresh()->current_newsletter_id)->toBe($newsletter->id)
        ->and(app(Entitlements::class)->issuesUsedThisWeek($user))->toBe(0);
});

test('kits respect the publication limit', function () {
    $kit = makeKit();
    $user = buyer(Product::Oto5Dfy);
    Newsletter::factory()->for($user)->count(5)->create();
    $this->actingAs($user);

    Livewire::test(Kits::class)->call('install', $kit->id)->assertDispatched('toast', tone: 'warning');
    expect(Newsletter::count())->toBe(5);
});

test('the seeded kits are valid and installable', function () {
    if (! file_exists(database_path('seeders/data/kits.php'))) {
        $this->markTestSkipped('Kit content not written yet.');
    }

    $this->seed(KitSeeder::class);

    expect(Kit::count())->toBeGreaterThanOrEqual(20);

    Kit::all()->each(function (Kit $kit) {
        expect($kit->topics)->not->toBeEmpty()->and($kit->seed_issues)->not->toBeEmpty();
        foreach ($kit->seed_issues as $seed) {
            expect(Blocks::normalize($seed['blocks']))->not->toBeEmpty();
        }
    });
});

test('multi-newsletter buyers switch between publications', function () {
    $user = buyer(Product::Oto9Multi);
    [$first, $second] = Newsletter::factory()->for($user)->count(2)->create();
    $this->actingAs($user);

    $this->post(route('publications.switch', $second))->assertRedirect(route('dashboard'));
    expect($user->fresh()->activeNewsletter()->id)->toBe($second->id);

    $this->get(route('dashboard'))->assertSee($second->name);
});

test('a buyer cannot switch to someone else\'s publication', function () {
    $user = buyer(Product::Oto9Multi);
    $this->actingAs($user)->post(route('publications.switch', Newsletter::factory()->create()))->assertForbidden();
});

test('network members swap recommendations that show on both public pages', function () {
    $mine = Newsletter::factory()->for(buyer(Product::Oto10Network))->create(['name' => 'Mine Weekly']);
    $theirs = Newsletter::factory()->for(buyer(Product::Oto10Network))->create(['name' => 'Theirs Weekly']);
    Issue::factory()->for($theirs)->published()->create();
    $theirListing = $theirs->networkListing()->create(['category' => 'money', 'pitch' => 'Their pitch']);

    $this->actingAs($mine->user);
    Livewire::test(Network::class)->set('category', 'money')->set('pitch', 'My pitch')->call('saveListing')->call('requestSwap', $theirListing->id);

    $swap = NetworkSwap::sole();
    expect($swap->status)->toBe(SwapStatus::Pending);

    $this->actingAs($theirs->user);
    Livewire::test(Network::class)->call('respond', $swap->id, true);
    expect($swap->fresh()->status)->toBe(SwapStatus::Accepted);

    $this->get(publicationUrl($mine))->assertSee('Newsletters we recommend')->assertSee('Theirs Weekly');
    $this->get(publicationUrl($theirs))->assertSee('Mine Weekly');
});

test('swaps with admin-seeded listings are accepted straight away', function () {
    $seeded = Newsletter::factory()->create();
    $seededListing = $seeded->networkListing()->create(['category' => 'tech', 'pitch' => 'Seeded', 'is_seeded' => true]);
    $mine = Newsletter::factory()->for(buyer(Product::Oto10Network))->create();
    $this->actingAs($mine->user);

    Livewire::test(Network::class)->call('saveListing')->call('requestSwap', $seededListing->id);

    expect(NetworkSwap::sole()->status)->toBe(SwapStatus::Accepted);
});

test('recommendations disappear when the network upgrade is refunded', function () {
    $mine = Newsletter::factory()->create();
    $partner = Newsletter::factory()->create(['name' => 'Partner Weekly']);
    $a = $mine->networkListing()->create(['category' => 'tech', 'pitch' => 'a']);
    $b = $partner->networkListing()->create(['category' => 'tech', 'pitch' => 'b']);
    NetworkSwap::create(['requester_listing_id' => $a->id, 'recipient_listing_id' => $b->id, 'status' => SwapStatus::Accepted]);

    $this->get(publicationUrl($mine))->assertDontSee('Partner Weekly');
});

test('the network seeder fills the directory', function () {
    $this->seed(NetworkSeeder::class);

    expect(NetworkListing::where('is_seeded', true)->count())->toBeGreaterThanOrEqual(6);
});

test('agencies manage clients with packages and get a filled-in contract', function () {
    if (! config('agency.packages')) {
        $this->markTestSkipped('Agency content not written yet.');
    }

    $user = buyer(Product::Oto11Agency);
    $newsletter = Newsletter::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Agency::class)
        ->call('create')
        ->set('name', 'Acme Bakery')
        ->set('contactEmail', 'owner@acme.test')
        ->set('package', 'growth')
        ->set('contractStatus', 'signed')
        ->set('newsletterIds', [$newsletter->id])
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::sole();
    expect($client->contract_status)->toBe(ContractStatus::Signed)
        ->and($client->newsletters()->pluck('newsletters.id')->all())->toBe([$newsletter->id]);

    Livewire::test(Agency::class)->call('showContract', $client->id)->assertSee('Acme Bakery')->assertDontSee('{client_name}');
});

test('agencies cannot attach someone else\'s publication to a client', function () {
    $user = buyer(Product::Oto11Agency);
    $this->actingAs($user);

    Livewire::test(Agency::class)
        ->set('name', 'Acme')
        ->set('package', 'starter')
        ->set('monthlyFee', '100')
        ->set('newsletterIds', [Newsletter::factory()->create()->id])
        ->call('save')
        ->assertHasErrors('newsletterIds.0');
});

test('resellers create codes within their quota', function () {
    config(['newspub.limits.reseller_codes_per_license' => 5]);
    $reseller = buyer(Product::Oto12Reseller);
    $this->actingAs($reseller);

    Livewire::test(Reseller::class)->set('quantity', 3)->call('generate')->assertHasNoErrors();
    expect(LicenseCode::count())->toBe(3)
        ->and(LicenseCode::first()->code)->toMatch('/^NPAI-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');

    Livewire::test(Reseller::class)->set('quantity', 3)->call('generate')->assertHasErrors('quantity');
    expect(LicenseCode::count())->toBe(3);
});

test('a buyer redeems a reseller code on the white-label page and gets the front end', function () {
    $reseller = buyer(Product::Oto12Reseller);
    $reseller->forceFill(['reseller_slug' => 'inbox-pro', 'reseller_brand' => 'Inbox Pro'])->save();
    $code = $reseller->licenseCodes()->create(['code' => 'NPAI-AAAA-BBBB-CCCC']);

    $this->get(appUrl('redeem/inbox-pro'))->assertOk()->assertSee('Inbox Pro');

    $this->post(appUrl('redeem/inbox-pro'), [
        'code' => 'npai-aaaa-bbbb-cccc',
        'name' => 'New Buyer',
        'email' => 'new@example.com',
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
        'terms' => '1',
    ])->assertRedirect(route('dashboard'));

    $buyer = User::where('email', 'new@example.com')->sole();
    expect($buyer->owns(Product::FrontEnd))->toBeTrue()
        ->and($buyer->purchases()->sole()->source)->toBe(PurchaseSource::LicenseCode)
        ->and($code->fresh()->redeemed_by)->toBe($buyer->id);
});

test('a code works only once and only on its own reseller page', function () {
    $reseller = buyer(Product::Oto12Reseller);
    $other = buyer(Product::Oto12Reseller);
    $other->forceFill(['reseller_slug' => 'other'])->save();
    $reseller->licenseCodes()->create(['code' => 'NPAI-AAAA-BBBB-CCCC']);
    $redeem = app(RedeemLicenseCode::class);

    expect(fn () => $redeem->handle('NPAI-AAAA-BBBB-CCCC', 'X', 'x@example.com', 'secret-pass-1', $other))
        ->toThrow(ValidationException::class);

    $redeem->handle('NPAI-AAAA-BBBB-CCCC', 'X', 'x@example.com', 'secret-pass-1');

    expect(fn () => $redeem->handle('NPAI-AAAA-BBBB-CCCC', 'Y', 'y@example.com', 'secret-pass-1'))
        ->toThrow(ValidationException::class);
});

test('admins grant and revoke through the ledger', function () {
    $admin = User::factory()->admin()->owning()->create();
    $user = buyer();
    $this->actingAs($admin);

    Livewire::test(AdminUsers::class)->call('select', $user->id)->set('grantProduct', 'oto6_social')->call('grant');
    expect($user->fresh()->owns(Product::Oto6Social))->toBeTrue();

    $purchase = Purchase::where('user_id', $user->id)->where('product_key', 'oto6_social')->sole();
    expect($purchase->source)->toBe(PurchaseSource::AdminGrant);

    Livewire::test(AdminUsers::class)->call('select', $user->id)->call('revoke', $purchase->id);
    expect($user->fresh()->owns(Product::Oto6Social))->toBeFalse();
});

test('admins can take a reported page offline', function () {
    $admin = User::factory()->admin()->owning()->create();
    $newsletter = Newsletter::factory()->create();
    $this->actingAs($admin);

    Livewire::test(Reports::class)->call('takeOffline', $newsletter->id);

    $this->get(publicationUrl($newsletter))->assertStatus(410);
});

test('the spend alert emails admins when daily AI spend passes the threshold', function () {
    Notification::fake();
    config(['newspub.ai.daily_spend_alert' => 10]);
    $admin = User::factory()->admin()->create();
    AiUsage::create(['kind' => 'issue', 'input_tokens' => 1, 'output_tokens' => 1, 'cost' => 12.5, 'created_at' => now()]);

    $this->artisan('newspub:check-ai-spend')->assertSuccessful();

    Notification::assertSentTo($admin, AiSpendAlert::class);
});
