<?php

namespace Database\Factories;

use App\Models\BumpOffer;
use App\Models\BumpOfferFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BumpOfferFile>
 */
class BumpOfferFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bump_offer_id' => BumpOffer::factory(),
            'name' => 'Guide.pdf',
            'path' => 'bumps/'.Str::random(12).'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 204800,
            'sort' => 0,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Graphic.png',
            'path' => 'bumps/'.Str::random(12).'.png',
            'mime_type' => 'image/png',
        ]);
    }
}
