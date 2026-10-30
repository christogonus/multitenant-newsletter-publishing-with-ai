<?php

use App\Enums\Product;
use App\Models\BumpOffer;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\UpgradeUnlocked;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    configureLaunchpad();
    Notification::fake();
});

test('buyers who took the bump before it was set up get it, the vault it unlocks and an email, and a second run changes nothing', function () {
    postIpn(ipnPayload(['bump' => 'true', 'total_amount' => '34.00']))->assertOk();
    $vault = BumpOffer::factory()->create(['price' => 17, 'unlocks_product' => Product::BumpVault]);

    $this->artisan('launchpad:backfill-bumps')->expectsOutputToContain('Gave 1 buyer their order bump.')->assertSuccessful();

    $user = User::sole();
    expect($vault->isOwnedBy($user))->toBeTrue()
        ->and($user->owns(Product::BumpVault))->toBeTrue()
        ->and(Purchase::where('product_key', Product::FrontEnd)->sole()->amount)->toBe('17.00')
        ->and(Purchase::where('bump_offer_id', $vault->id)->sole()->amount)->toBe('17.00');
    Notification::assertSentTo($user, UpgradeUnlocked::class, fn (UpgradeUnlocked $notification) => $notification->transactionId === 'pi_1001');

    $this->artisan('launchpad:backfill-bumps')->expectsOutputToContain('Gave 0 buyers their order bump.')->assertSuccessful();
    expect(Purchase::count())->toBe(3);
});

test('sales without the bump, refunded sales and buyers who already own the bump are left alone', function () {
    postIpn(ipnPayload(['transaction_id' => 'pi_no_bump', 'user' => ['email' => 'no-bump@example.com']]));
    postIpn(ipnPayload(['transaction_id' => 'pi_refunded', 'bump' => 'true', 'user' => ['email' => 'refunded@example.com']]));
    postIpn(ipnPayload(['transaction_id' => 'pi_refunded', 'action' => 'REFUND', 'user' => ['email' => 'refunded@example.com']]));
    postIpn(ipnPayload(['transaction_id' => 'pi_granted', 'bump' => 'true', 'user' => ['email' => 'granted@example.com']]));
    $bumpOffer = BumpOffer::factory()->create();
    Purchase::factory()->for(User::where('email', 'granted@example.com')->sole())->create(['transaction_id' => 'ADMIN-1', 'product_key' => null, 'bump_offer_id' => $bumpOffer->id]);

    $this->artisan('launchpad:backfill-bumps')->expectsOutputToContain('Gave 0 buyers their order bump.')->assertSuccessful();

    expect(Purchase::whereNotNull('bump_offer_id')->count())->toBe(1);
    Notification::assertNotSentTo(User::all(), UpgradeUnlocked::class);
});

test('a dry run only lists the buyers, and products with no bump set up are reported', function () {
    postIpn(ipnPayload(['bump' => 'true']));
    postIpn(ipnPayload(['transaction_id' => 'pi_upsell', 'product_id' => 3003, 'bump' => 'true']));
    BumpOffer::factory()->create();

    $this->artisan('launchpad:backfill-bumps --dry-run')
        ->expectsOutputToContain('buyer@example.com on '.Product::FrontEnd->label())
        ->expectsOutputToContain('1 buyer would get their order bump.')
        ->expectsOutputToContain('1 buyer took the bump on '.Product::Oto1Delivery->label())
        ->assertSuccessful();

    expect(Purchase::whereNotNull('bump_offer_id')->count())->toBe(0);
});
