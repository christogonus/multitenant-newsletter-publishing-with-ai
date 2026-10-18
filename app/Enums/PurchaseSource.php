<?php

namespace App\Enums;

enum PurchaseSource: string
{
    case WarriorPlus = 'warriorplus';
    case LicenseCode = 'license_code';
    case AdminGrant = 'admin_grant';
}
