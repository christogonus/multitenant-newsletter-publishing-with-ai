<?php

namespace App\Enums;

enum PurchaseSource: string
{
    case LaunchpadJv = 'launchpadjv';
    case LicenseCode = 'license_code';
    case AdminGrant = 'admin_grant';
}
