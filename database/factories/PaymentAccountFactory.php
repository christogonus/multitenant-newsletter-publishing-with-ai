<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Enums\PaymentProvider;
use App\Models\Newsletter;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_id' => Newsletter::factory(),
            'provider' => PaymentProvider::Stripe,
            'credentials' => ['secret_key' => 'sk_test_123'],
            'status' => ConnectionStatus::Connected,
        ];
    }

    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => PaymentProvider::PayPal,
            'credentials' => ['client_id' => 'paypal-client', 'client_secret' => 'paypal-secret', 'mode' => 'sandbox'],
        ]);
    }
}
