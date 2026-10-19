<?php

namespace Database\Factories;

use App\Enums\PaidPlan;
use App\Enums\PaymentClaimStatus;
use App\Models\Newsletter;
use App\Models\PaymentClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentClaim>
 */
class PaymentClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_id' => Newsletter::factory(),
            'email' => fake()->unique()->safeEmail(),
            'plan' => PaidPlan::Monthly,
            'reference' => fake()->bothify('TX-####-????'),
            'status' => PaymentClaimStatus::Pending,
        ];
    }
}
