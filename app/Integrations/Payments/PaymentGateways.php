<?php

namespace App\Integrations\Payments;

use App\Enums\PaymentProvider;
use InvalidArgumentException;

class PaymentGateways
{
    public function for(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider) {
            PaymentProvider::Stripe => app(StripeGateway::class),
            PaymentProvider::PayPal => app(PayPalGateway::class),
            PaymentProvider::Manual => throw new InvalidArgumentException('Manual access has no payment gateway.'),
        };
    }
}
