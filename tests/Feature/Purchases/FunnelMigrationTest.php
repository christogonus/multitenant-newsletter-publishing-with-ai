<?php

use App\Enums\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

test('Starter + Vault buyers stored as locked out regain the app after the migration', function () {
    $buyer = User::factory()->create();
    Feature::for($buyer)->deactivate(Product::FrontEnd->value);
    Purchase::factory()->for($buyer)->create(['product_key' => Product::BumpVault]);
    Feature::flushCache();

    (require database_path('migrations/2026_10_30_182320_forget_stored_front_end_access.php'))->up();
    Feature::flushCache();

    expect($buyer->owns(Product::FrontEnd))->toBeTrue();
});

/**
 * Writes a ledger row with a product key the enum no longer knows, the way it sits in the database before migrating.
 */
function legacySale(User $user, string $productKey, string $transactionId): void
{
    DB::table('purchases')->insert([
        'user_id' => $user->id,
        'buyer_email' => $user->email,
        'source' => 'launchpadjv',
        'transaction_id' => $transactionId,
        'product_key' => $productKey,
        'item_key' => $productKey,
        'amount' => 97,
        'action' => 'sale',
        'payload' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('lite and pro buyers get delivery, and unlimited buyers get gold', function () {
    $lite = User::factory()->create();
    $pro = User::factory()->create();
    $both = User::factory()->create();
    $unlimited = User::factory()->create();

    legacySale($lite, 'oto1_lite', 'pi_lite');
    legacySale($pro, 'oto1_pro', 'pi_pro');
    legacySale($both, 'oto1_lite', 'pi_both');
    legacySale($both, 'oto1_pro', 'pi_both');
    legacySale($unlimited, 'oto2_unlimited', 'pi_unlimited');

    (require database_path('migrations/2026_10_19_013805_move_purchases_to_the_v1_1_funnel.php'))->up();

    expect($lite->owns(Product::Oto1Delivery))->toBeTrue()
        ->and($pro->owns(Product::Oto1Delivery))->toBeTrue()
        ->and($both->owns(Product::Oto1Delivery))->toBeTrue()
        ->and($both->purchases()->count())->toBe(1)
        ->and($unlimited->owns(Product::Oto2Gold))->toBeTrue()
        ->and($unlimited->owns(Product::Oto2Silver))->toBeTrue()
        ->and(DB::table('purchases')->whereIn('product_key', ['oto1_lite', 'oto1_pro', 'oto2_unlimited'])->exists())->toBeFalse();
});
