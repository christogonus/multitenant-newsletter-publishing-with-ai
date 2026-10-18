<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto1Pro
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto1_pro';
}
