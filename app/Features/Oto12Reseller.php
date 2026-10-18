<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto12Reseller
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto12_reseller';
}
