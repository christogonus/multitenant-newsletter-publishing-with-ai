<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto2Silver
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto2_silver';
}
