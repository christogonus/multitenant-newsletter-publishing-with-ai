<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto11Agency
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto11_agency';
}
