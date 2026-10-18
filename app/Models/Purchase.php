<?php

namespace App\Models;

use App\Enums\Product;
use App\Enums\PurchaseAction;
use App\Enums\PurchaseSource;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per IPN event. The ledger is the single source of truth for what a buyer owns.
 */
#[Fillable(['user_id', 'source', 'wp_txn_id', 'wp_product_id', 'product_key', 'amount', 'action', 'payload'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'product_key' => Product::class,
            'action' => PurchaseAction::class,
            'source' => PurchaseSource::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sales that have not been refunded or charged back.
     *
     * @param  Builder<Purchase>  $query
     */
    public function scopeStanding(Builder $query): void
    {
        $query->where('purchases.action', PurchaseAction::Sale)
            ->whereNotExists(function ($reversals) {
                $reversals->selectRaw('1')
                    ->from('purchases as reversals')
                    ->whereColumn('reversals.wp_txn_id', 'purchases.wp_txn_id')
                    ->whereIn('reversals.action', [PurchaseAction::Refund->value, PurchaseAction::Chargeback->value]);
            });
    }
}
