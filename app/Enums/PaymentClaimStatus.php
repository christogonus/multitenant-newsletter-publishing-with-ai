<?php

namespace App\Enums;

enum PaymentClaimStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
