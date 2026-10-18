<?php

namespace Database\Factories;

use App\Enums\Frequency;
use App\Enums\Product;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Newsletter>
 */
class NewsletterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' Weekly';

        return [
            'user_id' => User::factory()->owning(Product::FrontEnd),
            'name' => $name,
            'tagline' => fake()->sentence(6),
            'niche' => fake()->randomElement(['personal finance', 'AI tools', 'gardening', 'travel']),
            'audience' => 'Curious beginners',
            'frequency' => Frequency::Weekly,
            'subdomain' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'template' => 'classic',
        ];
    }
}
