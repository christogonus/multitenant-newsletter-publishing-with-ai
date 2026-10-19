<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto2Gold
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto2_gold';
}
