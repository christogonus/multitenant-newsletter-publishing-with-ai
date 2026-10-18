<?php

namespace Database\Factories;

use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_id' => Newsletter::factory(),
            'email' => fake()->unique()->safeEmail(),
            'source' => 'hosted_page',
            'consent_at' => now(),
            'consent_ip' => fake()->ipv4(),
        ];
    }
}
