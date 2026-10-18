<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto4Sponsor
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto4_sponsor';
}
