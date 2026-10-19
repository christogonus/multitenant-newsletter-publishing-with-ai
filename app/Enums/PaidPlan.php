<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum PaidPlan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The per-period word used after a price, as in "$5 a month".
     */
    public function period(): string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Yearly => 'year',
        };
    }

    /**
     * One billing period after the given time, used when a payment service does not say when the next payment is due.
     */
    public function after(CarbonInterface $start): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $start->copy()->addMonth(),
            self::Yearly => $start->copy()->addYear(),
        };
    }
}
