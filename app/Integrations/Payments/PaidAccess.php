<?php

namespace App\Integrations\Payments;

use App\Enums\PaidPlan;
use Carbon\CarbonInterface;

/**
 * A confirmed subscription: the provider's subscription ID and when the paid period ends.
 */
final readonly class PaidAccess
{
    public function __construct(
        public string $reference,
        public CarbonInterface $paidUntil,
        public PaidPlan $plan,
    ) {}
}
