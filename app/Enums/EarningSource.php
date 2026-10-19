<?php

namespace App\Enums;

enum EarningSource: string
{
    case Sponsor = 'sponsor';
    case PaidSubscriptions = 'paid_subscriptions';
    case Affiliate = 'affiliate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sponsor => 'Sponsor',
            self::PaidSubscriptions => 'Paid subscriptions',
            self::Affiliate => 'Affiliate or promotion',
            self::Other => 'Other',
        };
    }
}
