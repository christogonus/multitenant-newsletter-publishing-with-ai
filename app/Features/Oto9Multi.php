<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto9Multi
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto9_multi';
}
