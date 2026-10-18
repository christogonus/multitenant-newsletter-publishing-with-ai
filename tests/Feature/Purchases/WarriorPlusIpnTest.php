<?php

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    configureWarriorPlus();
    Notification::fake();
});

test('a sale creates the buyer, records the purchase and unlocks the front end', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload())->assertOk()->assertSee('OK');

    $user = User::where('email', 'buyer@example.com')->sole();

    expect($user->name)->toBe('Pat Buyer')
        ->and($user->owns(Product::FrontEnd))->toBeTrue()
        ->and($user->owns(Product::Oto1Pro))->toBeFalse();

    $purchase = Purchase::sole();
    expect($purchase->action)->toBe(PurchaseAction::Sale)
        ->and($purchase->product_key)->toBe(Product::FrontEnd)
        ->and($purchase->payload)->not->toHaveKey('WP_SECURITYKEY');

    Notification::assertSentTo($user, WelcomeBuyer::class);
});

test('the same IPN delivered twice records one purchase and one welcome email', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload())->assertOk();
    $this->post(appUrl('ipn/warriorplus'), ipnPayload())->assertOk()->assertSee('Duplicate');

    expect(Purchase::count())->toBe(1);
    Notification::assertSentTimes(WelcomeBuyer::class, 1);
});

test('a refund revokes the feature it bought', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload());
    $user = User::where('email', 'buyer@example.com')->sole();
    expect($user->owns(Product::FrontEnd))->toBeTrue();

    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['action' => 'refund']))->assertOk();

    expect($user->fresh()->owns(Product::FrontEnd))->toBeFalse()
        ->and(Purchase::where('action', PurchaseAction::Refund)->count())->toBe(1);
});

test('a chargeback revokes only the product that was charged back', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload());
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['product_id' => 'wso_oto1_pro', 'transaction_id' => 'TXN-2002']));
    $user = User::where('email', 'buyer@example.com')->sole();
    expect($user->owns(Product::Oto1Pro))->toBeTrue();

    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['action' => 'chargeback', 'product_id' => 'wso_oto1_pro', 'transaction_id' => 'TXN-2002']));

    $user = $user->fresh();
    expect($user->owns(Product::Oto1Pro))->toBeFalse()
        ->and($user->owns(Product::Oto1Lite))->toBeFalse()
        ->and($user->owns(Product::FrontEnd))->toBeTrue();
});

test('a second purchase by an existing buyer does not create another account or welcome email', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload());
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['product_id' => 'wso_oto2_unlimited', 'transaction_id' => 'TXN-3003', 'buyer_email' => 'BUYER@example.com']));

    expect(User::count())->toBe(1)
        ->and(User::sole()->owns(Product::Oto2Unlimited))->toBeTrue();
    Notification::assertSentTimes(WelcomeBuyer::class, 1);
});

test('pro grants lite but lite does not grant pro', function () {
    $pro = buyer(Product::Oto1Pro);
    $lite = buyer(Product::Oto1Lite);

    expect($pro->owns(Product::Oto1Lite))->toBeTrue()
        ->and($lite->owns(Product::Oto1Pro))->toBeFalse();
});

test('an IPN with the wrong security key is rejected', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['security_key' => 'nope']))->assertForbidden();

    expect(User::count())->toBe(0)->and(Purchase::count())->toBe(0);
});

test('an IPN is rejected when no security key is configured', function () {
    config(['products.warriorplus.security_key' => null]);

    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['security_key' => '']))->assertForbidden();
});

test('an unknown product is acknowledged and ignored', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['product_id' => 'someone-elses-product']))->assertOk()->assertSee('Ignored');

    expect(User::count())->toBe(0);
});

test('a refund for an unknown buyer creates nothing', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['action' => 'refund']))->assertOk();

    expect(User::count())->toBe(0)->and(Purchase::count())->toBe(0);
});

test('an IPN without a buyer email is rejected', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload(['buyer_email' => 'not-an-email']))->assertStatus(422);
});

test('the welcome link lets the buyer set a password, accept the terms and land on the dashboard', function () {
    $this->post(appUrl('ipn/warriorplus'), ipnPayload());
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
    $this->post(appUrl('ipn/warriorplus'), ipnPayload());
    $url = WelcomeBuyer::setPasswordUrl(User::sole());

    $this->get($url.'x')->assertForbidden();
});
