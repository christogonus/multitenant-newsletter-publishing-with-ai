<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class FrontEnd
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'front_end';
}
