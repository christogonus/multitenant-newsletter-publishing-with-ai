<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto2Unlimited
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto2_unlimited';
}
