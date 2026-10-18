<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use App\Models\Newsletter;
use App\Models\PlatformConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformConnection>
 */
class PlatformConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_id' => Newsletter::factory(),
            'user_id' => fn (array $attributes) => Newsletter::find($attributes['newsletter_id'])->user_id,
            'platform' => Platform::Kit,
            'credentials' => ['api_key' => 'test-key'],
            'status' => ConnectionStatus::Connected,
        ];
    }
}
