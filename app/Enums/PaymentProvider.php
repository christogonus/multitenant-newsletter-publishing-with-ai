<?php

namespace App\Enums;

/**
 * How a reader paid for access. Stripe and PayPal are accounts the owner connects; Manual is access the owner gave by hand.
 */
enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Manual => 'Added by you',
        };
    }

    /**
     * The providers an owner can connect for checkout.
     *
     * @return list<self>
     */
    public static function connectable(): array
    {
        return [self::Stripe, self::PayPal];
    }
}
