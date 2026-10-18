<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto3Growth
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto3_growth';
}
