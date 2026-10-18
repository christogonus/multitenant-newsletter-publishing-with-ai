<?php

namespace Database\Factories;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => PurchaseSource::LaunchpadJv,
            'transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
            'gateway_product_id' => (string) fake()->numberBetween(100000, 999999),
            'product_key' => Product::FrontEnd,
            'amount' => 17,
            'action' => PurchaseAction::Sale,
            'payload' => [],
        ];
    }
}
