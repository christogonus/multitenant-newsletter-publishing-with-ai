<?php

namespace Database\Factories;

use App\Models\ReservedSubdomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservedSubdomain>
 */
class ReservedSubdomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('reserved-?????'),
        ];
    }
}
