<?php

namespace App\Models;

use App\Enums\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A redemption code a reseller (OTO 12) sells on their own checkout.
 */
#[Fillable(['reseller_id', 'code', 'product_key', 'redeemed_by', 'redeemed_at', 'revoked_at'])]
class LicenseCode extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'product_key' => Product::class,
            'redeemed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /**
     * @param  Builder<LicenseCode>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereNull('redeemed_at')->whereNull('revoked_at');
    }

    public function isAvailable(): bool
    {
        return $this->redeemed_at === null && $this->revoked_at === null;
    }
}
