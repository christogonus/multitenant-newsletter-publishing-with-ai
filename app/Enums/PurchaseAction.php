<?php

namespace App\Enums;

enum PurchaseAction: string
{
    case Sale = 'sale';
    case Refund = 'refund';
    case Chargeback = 'chargeback';

    public function revokes(): bool
    {
        return $this !== self::Sale;
    }
}
