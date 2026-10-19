<?php

namespace Database\Factories;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'terms_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }

    /**
     * Give the user standing sales in the ledger for the given products (Front End is always included).
     */
    public function owning(Product ...$products): static
    {
        return $this->afterCreating(function (User $user) use ($products) {
            $products = array_unique([Product::FrontEnd, ...$products], SORT_REGULAR);

            foreach ($products as $product) {
                $user->purchases()->create([
                    'source' => PurchaseSource::LaunchpadJv,
                    'transaction_id' => 'TEST-'.Str::upper(Str::random(12)),
                    'product_key' => $product,
                    'amount' => 17,
                    'action' => PurchaseAction::Sale,
                    'payload' => [],
                ]);
            }
        });
    }
}
