<?php

use App\Enums\Product;
use App\Livewire\Admin\Users;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\UpgradeUnlocked;
use App\Notifications\WelcomeBuyer;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    configureLaunchpad();
    Notification::fake();
});

/**
 * A customer who bought the front end and has set their password.
 */
function activatedBuyer(): User
{
    postIpn(ipnPayload());

    $buyer = User::sole();
    $buyer->forceFill(['email_verified_at' => now()])->save();

    return $buyer;
}

test('an activated customer gets one upgrade email, not a welcome email, when an upgrade comes in', function () {
    $buyer = activatedBuyer();

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto4Sponsor), 'transaction_id' => 'pi_4004']))->assertOk();

    Notification::assertSentTimes(WelcomeBuyer::class, 1);
    Notification::assertSentTo($buyer, UpgradeUnlocked::class, function (UpgradeUnlocked $notification) use ($buyer) {
        $mail = $notification->toMail($buyer);

        return $notification->transactionId === 'pi_4004'
            && $mail->subject === 'Sponsor and Payday Vault is unlocked'
            && $mail->actionUrl === route('sponsors.index');
    });
});

test('an upgrade with an order bump sends one email listing both', function () {
    $buyer = activatedBuyer();
    BumpOffer::factory()->forProduct(Product::Oto3Growth)->create(['name' => 'Growth Swipe Pack', 'description' => '50 proven sign-up headlines.']);

    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto3Growth), 'transaction_id' => 'pi_3003', 'bump' => 'true', 'total_amount' => '124.00']));

    Notification::assertSentToTimes($buyer, UpgradeUnlocked::class, 1);
    $mail = Notification::sent($buyer, UpgradeUnlocked::class)->first()->toMail($buyer);
    $text = implode("\n", $mail->introLines);

    expect($mail->subject)->toBe('Your upgrades are unlocked')
        ->and($text)->toContain('Subscriber Growth Engine')->toContain('Growth Swipe Pack')->toContain('50 proven sign-up headlines.');
});

test('new buyers and redelivered IPNs get no upgrade email', function () {
    postIpn(ipnPayload());
    User::sole()->forceFill(['email_verified_at' => now()])->save();
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto1Delivery), 'transaction_id' => 'pi_2002']));
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto1Delivery), 'transaction_id' => 'pi_2002']))->assertSee('Duplicate');

    Notification::assertSentTimes(UpgradeUnlocked::class, 1);
});

test('no upgrade email goes out for a purchase that was refunded before it was sent', function () {
    $buyer = activatedBuyer();
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto6Social), 'transaction_id' => 'pi_6006']));
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto6Social), 'transaction_id' => 'pi_6006', 'action' => 'REFUND']));

    expect((new UpgradeUnlocked('pi_6006'))->shouldSend($buyer->fresh(), 'mail'))->toBeFalse();
});

test('admins resend the welcome email and an upgrade email from the buyer panel', function () {
    $buyer = activatedBuyer();
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto6Social), 'transaction_id' => 'pi_6006']));
    $purchase = Purchase::query()->where('transaction_id', 'pi_6006')->sole();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Users::class)
        ->call('select', $buyer->id)
        ->call('resendWelcome')
        ->call('resendUpgrade', $purchase->id);

    Notification::assertSentToTimes($buyer, WelcomeBuyer::class, 2);
    Notification::assertSentToTimes($buyer, UpgradeUnlocked::class, 2);
});

test('only admins can resend buyer emails', function () {
    $buyer = activatedBuyer();
    $this->actingAs(User::factory()->create());

    Livewire::test(Users::class)->set('selectedId', $buyer->id)->call('resendWelcome')->assertForbidden();

    Notification::assertSentToTimes($buyer, WelcomeBuyer::class, 1);
});

test('admins preview the welcome and upgrade emails in the browser without sending them', function () {
    $buyer = activatedBuyer();
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto4Sponsor), 'transaction_id' => 'pi_4004']));
    $purchase = Purchase::query()->where('transaction_id', 'pi_4004')->sole();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.emails.welcome', $buyer))->assertOk()->assertSee('Choose my password')->assertSee($buyer->name);
    $this->get(route('admin.emails.upgrade', $purchase))->assertOk()->assertSee('Sponsor and Payday Vault')->assertSee(route('sponsors.index'));

    Notification::assertSentToTimes($buyer, WelcomeBuyer::class, 1);
    Notification::assertSentToTimes($buyer, UpgradeUnlocked::class, 1);
});

test('refunded purchases have no upgrade email to preview', function () {
    activatedBuyer();
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto6Social), 'transaction_id' => 'pi_6006']));
    postIpn(ipnPayload(['product_id' => launchpadId(Product::Oto6Social), 'transaction_id' => 'pi_6006', 'action' => 'REFUND']));
    $sale = Purchase::query()->where('transaction_id', 'pi_6006')->oldest('id')->first();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.emails.upgrade', $sale))->assertNotFound();
});

test('only admins can preview buyer emails', function () {
    $buyer = activatedBuyer();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.emails.welcome', $buyer))->assertForbidden();
});
