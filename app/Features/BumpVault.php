<?php

namespace App\Features;

use App\Features\Concerns\ResolvesFromLedger;

class BumpVault
{
    use ResolvesFromLedger;

    /**
     * The stored name of the feature.
     */
    public string $name = 'bump_vault';
}
