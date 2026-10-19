<?php

namespace Database\Factories;

use App\Enums\EarningSource;
use App\Models\Earning;
use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Earning>
 */
class EarningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_id' => Newsletter::factory(),
            'source' => EarningSource::Sponsor,
            'description' => fake()->company(),
            'amount' => fake()->randomFloat(2, 50, 1000),
            'earned_on' => now()->toDateString(),
        ];
    }
}
