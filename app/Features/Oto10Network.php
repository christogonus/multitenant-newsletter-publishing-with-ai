<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto10Network
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto10_network';
}
