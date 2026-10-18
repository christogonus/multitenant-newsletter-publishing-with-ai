<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class Oto7Autopilot
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'oto7_autopilot';
}
