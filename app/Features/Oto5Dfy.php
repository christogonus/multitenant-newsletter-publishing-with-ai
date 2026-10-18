<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto5Dfy
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto5_dfy';
}
