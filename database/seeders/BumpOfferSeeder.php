<?php

namespace Database\Seeders;

use App\Enums\Product;
use App\Models\BumpOffer;
use Illuminate\Database\Seeder;

/**
 * The launch bump on the front end: the built-in Niche and Swipe Vault. Other bumps are set up in Admin, Order bumps.
 */
class BumpOfferSeeder extends Seeder
{
    public function run(): void
    {
        if (BumpOffer::query()->where('product_key', Product::FrontEnd)->exists()) {
            return;
        }

        BumpOffer::query()->create([
            'product_key' => Product::FrontEnd,
            'name' => Product::BumpVault->label(),
            'description' => Product::BumpVault->benefit(),
            'price' => 17,
            'unlocks_product' => Product::BumpVault,
            'is_active' => true,
        ]);
    }
}
