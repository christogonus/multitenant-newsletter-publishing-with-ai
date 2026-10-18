<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto8Premium
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto8_premium';
}
