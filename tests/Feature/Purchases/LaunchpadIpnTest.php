<?php

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\IpnNeedsAttention;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    configureLaunchpad();
    Notification::fake();
});

test('a sale creates the buyer, records the purchase and unlocks the front end', function () {
    postIpn(ipnPayload())->assertOk()->assertSee('OK');

    $user = User::where('email', 'buyer@example.com')->sole();

    expect($user->name)->toBe('Pat Buyer')
        ->and($user->owns(Product::FrontEnd))->toBeTrue()
        ->and($user->owns(Product::BumpVault))->toBeFalse()
        ->and($user->owns(Product::Oto1Delivery))->toBeFalse();

    $purchase = Purchase::sole();
    expect($purchase->action)->toBe(PurchaseAction::Sale)
        ->and($purchase->product_key)->toBe(Product::FrontEnd)
        ->and($purchase->transaction_id)->toBe('pi_1001')
        ->and($purchase->payload)->not->toHaveKey('key');

    Notification::assertSentTo($user, WelcomeBuyer::class);
});

test('a live sale without transaction_id or total_amount is recorded under its sale id', function () {
    postIpn([
        'id' => 128049,
        'product_id' => 3001,
        'offer_id' => 606,
        'vendor_id' => 2139,
        'quantity' => 1,
        'affiliate_user' => 976,
        'bump' => 'false',
        'coupon_code' => 'NewsPub25',
        'status' => 'success',
        'action' => 'SALE',
        'user' => ['id' => 8507, 'name' => 'Lee Live', 'email' => 'live-buyer@example.com'],
        'product' => ['id' => 3001, 'name' => 'NewsletterPub AI Starter'],
    ])->assertOk()->assertSee('OK');

    $user = User::where('email', 'live-buyer@example.com')->sole();

    expect($user->owns(Product::FrontEnd))->toBeTrue()
        ->and(Purchase::sole()->transaction_id)->toBe('128049');

    Notification::assertSentTo($user, WelcomeBuyer::class);
});

test('a test sale where the vendor is the buyer is ignored', function () {
    postIpn(ipnPayload(['user_id' => 2139, 'vendor_id' => 2139, 'user' => ['id' => 2139]]))->assertOk()->assertSee('Ignored');

    expect(User::count())->toBe(0)
        ->and(Purchase::count())->toBe(0);
    Notification::assertNothingSent();
});

test('the action is read case-insensitively and "Purchase" counts as a sale', function () {
    postIpn(ipnPayload(['action' => 'Purchase']))->assertOk();

    expect(User::sole()->owns(Product::FrontEnd))->toBeTrue();
});

test('the checkout details are used when the LaunchpadJV account has no email', function () {
    postIpn(ipnPayload(['user' => ['email' => '', 'name' => ''], 'checkout_email' => 'Typed@Example.com', 'checkout_name' => 'Typed Name']))->assertOk();

    expect(User::sole()->only('email', 'name'))->toBe(['email' => 'typed@example.com', 'name' => 'Typed Name']);
});

test('a sale with the vault bump unlocks the vault too, splits the total and sends one welcome email', function () {
    $vault = BumpOffer::factory()->create(['price' => 17, 'unlocks_product' => Product::BumpVault]);

    postIpn(ipnPayload(['bump' => 'true', 'total_amount' => '34.00']))->assertOk();

    $user = User::sole();
    expect($user->owns(Product::FrontEnd))->toBeTrue()
        ->and($user->owns(Product::BumpVault))->toBeTrue()
        ->and($vault->isOwnedBy($user))->toBeTrue()
        ->and(Purchase::where('product_key', Product::FrontEnd)->sole()->amount)->toBe('17.00')
        ->and(Purchase::where('bump_offer_id', $vault->id)->sole()->amount)->toBe('17.00');

    Notification::assertSentTimes(WelcomeBuyer::class, 1);
});

test('a Starter + Vault sale opens both the app and the vault, without a missing-bump alert', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::BumpVault), 'bump' => 'true']))->assertOk();

    $buyer = User::where('email', 'buyer@example.com')->sole();
    expect($buyer->owns(Product::FrontEnd))->toBeTrue()
        ->and($buyer->owns(Product::BumpVault))->toBeTrue();
    $this->actingAs($buyer)->get(appUrl('dashboard'))->assertOk()->assertDontSee('access is not active');
    Notification::assertNotSentTo($admin, IpnNeedsAttention::class);

    postIpn(ipnPayload(['product_id' => launchpadId(Product::BumpVault), 'action' => 'REFUND']))->assertOk();

    expect($buyer->fresh()->owns(Product::FrontEnd))->toBeFalse()
        ->and($buyer->fresh()->owns(Product::BumpVault))->toBeFalse();
});

test('refunding a transaction with the order bump takes back both', function () {
    BumpOffer::factory()->create(['unlocks_product' => Product::BumpVault]);
    postIpn(ipnPayload(['bump' => 'true', 'total_amount' => '34.00']));

    postIpn(ipnPayload(['action' => 'REFUND']))->assertOk()->assertSee('OK');

    $user = User::sole();
    expect($user->owns(Product::FrontEnd))->toBeFalse()
        ->and($user->owns(Product::BumpVault))->toBeFalse()
        ->and(Purchase::where('action', PurchaseAction::Refund)->count())->toBe(3);
});

test('the same IPN delivered twice records one purchase and one welcome email', function () {
    BumpOffer::factory()->create(['unlocks_product' => Product::BumpVault]);
    postIpn(ipnPayload(['bump' => 'true']))->assertOk();
    postIpn(ipnPayload(['bump' => 'true']))->assertOk()->assertSee('Duplicate');

    expect(Purchase::count())->toBe(3);
    Notification::assertSentTimes(WelcomeBuyer::class, 1);

    postIpn(ipnPayload(['action' => 'REFUND']));
    postIpn(ipnPayload(['action' => 'REFUND']))->assertOk()->assertSee('Duplicate');

    expect(Purchase::where('action', PurchaseAction::Refund)->count())->toBe(3);
});

test('a refund revokes the feature it bought', function () {
    postIpn(ipnPayload());
    $user = User::where('email', 'buyer@example.com')->sole();
    expect($user->owns(Product::FrontEnd))->toBeTrue();

    postIpn(ipnPayload(['action' => 'refund']))->assertOk();

    expect($user->fresh()->owns(Product::FrontEnd))->toBeFalse()
        ->and(Purchase::where('action', PurchaseAction::Refund)->count())->toBe(1);
});

test('a chargeback revokes only the product that was charged back', function () {
    postIpn(ipnPayload());
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto1Delivery), 'transaction_id' => 'pi_2002']));
    $user = User::where('email', 'buyer@example.com')->sole();
    expect($user->owns(Product::Oto1Delivery))->toBeTrue();

    postIpn(ipnPayload(['action' => 'CHARGEBACK', 'product_id' => launchpadId(Product::Oto1Delivery), 'transaction_id' => 'pi_2002']));

    $user = $user->fresh();
    expect($user->owns(Product::Oto1Delivery))->toBeFalse()
        ->and($user->owns(Product::FrontEnd))->toBeTrue();
});

test('silver and gold sold as offers of one LaunchpadJV product are told apart by the offer', function () {
    config([
        'products.products.oto2_silver.launchpad_ids' => ['4000:11'],
        'products.products.oto2_gold.launchpad_ids' => ['4000:12'],
    ]);

    postIpn(ipnPayload(['product_id' => 4000, 'offer_id' => '12', 'transaction_id' => 'pi_3003']))->assertOk();

    expect(Purchase::sole()->product_key)->toBe(Product::Oto2Gold);
});

test('a second purchase by an activated buyer does not create another account or welcome email', function () {
    postIpn(ipnPayload());
    User::sole()->forceFill(['email_verified_at' => now()])->save();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto2Gold), 'transaction_id' => 'pi_3003', 'user' => ['email' => 'BUYER@example.com']]));

    expect(User::count())->toBe(1)
        ->and(User::sole()->owns(Product::Oto2Gold))->toBeTrue();
    Notification::assertSentTimes(WelcomeBuyer::class, 1);
});

test('a buyer who has not chosen a password yet gets the set-password link again with an upsell', function () {
    postIpn(ipnPayload());
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto2Gold), 'transaction_id' => 'pi_3003']));

    expect(User::count())->toBe(1);
    Notification::assertSentTimes(WelcomeBuyer::class, 2);
});

test('a buyer who changes their login email keeps getting upsells and refunds on the same account', function () {
    postIpn(ipnPayload());
    $user = User::sole();
    $user->forceFill(['email' => 'new-login@example.com', 'email_verified_at' => now()])->save();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto2Gold), 'transaction_id' => 'pi_3003']))->assertOk();
    postIpn(ipnPayload(['action' => 'REFUND']))->assertOk();

    $user = $user->fresh();
    expect(User::count())->toBe(1)
        ->and($user->owns(Product::Oto2Gold))->toBeTrue()
        ->and($user->owns(Product::FrontEnd))->toBeFalse()
        ->and(Purchase::pluck('buyer_email')->unique()->all())->toBe(['buyer@example.com']);
});

test('nobody can switch their login to an email another account paid with', function () {
    postIpn(ipnPayload());
    User::sole()->forceFill(['email' => 'new-login@example.com'])->save();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->put(route('user-profile-information.update'), ['name' => $other->name, 'email' => 'buyer@example.com'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

    expect($other->fresh()->email)->not->toBe('buyer@example.com');
});

test('gold grants silver but silver does not grant gold', function () {
    $gold = buyer(Product::Oto2Gold);
    $silver = buyer(Product::Oto2Silver);

    expect($gold->owns(Product::Oto2Silver))->toBeTrue()
        ->and($silver->owns(Product::Oto2Gold))->toBeFalse();
});

test('an IPN with the wrong key, or none, is rejected', function () {
    postIpn(ipnPayload(), 'nope')->assertForbidden();
    $this->postJson(appUrl('ipn/launchpad'), ipnPayload())->assertForbidden();

    expect(User::count())->toBe(0)->and(Purchase::count())->toBe(0);
});

test('every IPN is logged as it arrived, rejected ones included, without the webhook key', function () {
    $logged = [];
    Log::shouldReceive('channel')->with('launchpad')->andReturnSelf();
    Log::shouldReceive('info')->with('IPN received', Mockery::capture($context))->andReturnUsing(function () use (&$logged, &$context) {
        $logged[] = $context;
    });
    Log::shouldReceive('warning');

    postIpn(ipnPayload(), 'nope')->assertForbidden();
    $this->postJson(appUrl('ipn/launchpad'), ipnPayload(), ['X-Webhook-Key' => 'test-webhook-key'])->assertOk();

    expect($logged)->toHaveCount(2)
        ->and(json_decode($logged[0]['body'], true))->toBe(ipnPayload())
        ->and($logged[0]['query'])->not->toHaveKey('key')
        ->and($logged[1]['headers'])->not->toHaveKey('x-webhook-key')
        ->and(json_encode($logged))->not->toContain('test-webhook-key');
});

test('the key may be sent as a header instead', function () {
    $this->postJson(appUrl('ipn/launchpad'), ipnPayload(), ['X-Webhook-Key' => 'test-webhook-key'])->assertOk();

    expect(User::count())->toBe(1);
});

test('every IPN is refused while no key is configured', function () {
    config(['products.launchpad.webhook_key' => null]);

    postIpn(ipnPayload(), '')->assertStatus(503);

    expect(User::count())->toBe(0);
});

test('an unknown product is acknowledged and ignored', function () {
    postIpn(ipnPayload(['product_id' => 99999]))->assertOk()->assertSee('Ignored');

    expect(User::count())->toBe(0);
});

test('a refund for an unknown buyer creates nothing', function () {
    postIpn(ipnPayload(['action' => 'refund']))->assertOk();

    expect(User::count())->toBe(0)->and(Purchase::count())->toBe(0);
});

test('an IPN without a buyer email is rejected', function () {
    postIpn(ipnPayload(['user' => ['email' => 'not-an-email']]))->assertStatus(422);
});

test('the welcome link lets the buyer set a password, accept the terms and land on the dashboard', function () {
    postIpn(ipnPayload());
    $user = User::where('email', 'buyer@example.com')->sole();
    $url = WelcomeBuyer::setPasswordUrl($user);

    $this->get($url)->assertOk()->assertSee('Choose a password', false);

    $this->post($url, ['password' => 'a-strong-password-1', 'password_confirmation' => 'a-strong-password-1'])
        ->assertSessionHasErrors('terms');

    $this->post($url, ['password' => 'a-strong-password-1', 'password_confirmation' => 'a-strong-password-1', 'terms' => '1'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->terms_accepted_at)->not->toBeNull();

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

test('a tampered welcome link is rejected', function () {
    postIpn(ipnPayload());
    $url = WelcomeBuyer::setPasswordUrl(User::sole());

    $this->get($url.'x')->assertForbidden();
});
