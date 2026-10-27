<?php

namespace Database\Factories;

use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraphs(2, true),
            'audience' => ['mode' => 'everyone', 'items' => [], 'exclude_items' => [], 'include_demo' => false],
        ];
    }
}
