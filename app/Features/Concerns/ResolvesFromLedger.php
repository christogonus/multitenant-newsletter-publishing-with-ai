<?php

namespace App\Features\Concerns;

use App\Enums\Product;
use App\Models\User;

/**
 * Resolves a product feature from the purchases ledger. Never grant a feature any other way.
 */
trait ResolvesFromLedger
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $user): bool
    {
        $product = Product::from($this->name);

        return $user->purchases()
            ->standing()
            ->whereIn('product_key', array_map(fn (Product $granting) => $granting->value, $product->grantedBy()))
            ->exists();
    }
}
