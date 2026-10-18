<?php

namespace Database\Factories;

use App\Enums\Product;
use App\Models\BumpOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BumpOffer>
 */
class BumpOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_key' => Product::FrontEnd,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => 17,
            'unlocks_product' => null,
            'is_active' => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => ['product_key' => $product]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
