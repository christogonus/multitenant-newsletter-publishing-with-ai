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
 * One row per item (a product or an order bump) per IPN event. The ledger is the single source of truth for what a buyer owns.
 */
#[Fillable(['user_id', 'source', 'transaction_id', 'gateway_product_id', 'product_key', 'bump_offer_id', 'item_key', 'amount', 'action', 'payload'])]
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

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            if ($purchase->item_key === null || $purchase->item_key === '') {
                $purchase->item_key = self::itemKey($purchase->product_key, $purchase->bump_offer_id);
            }
        });
    }

    /**
     * "oto1_pro" for a product, "bump:12" for an order bump.
     */
    public static function itemKey(?Product $product, ?int $bumpOfferId): string
    {
        return $bumpOfferId !== null ? 'bump:'.$bumpOfferId : (string) $product?->value;
    }

    /**
     * What was bought, for admin screens.
     */
    public function itemLabel(): string
    {
        return $this->bump_offer_id !== null
            ? 'Order bump: '.($this->bumpOffer?->name ?? 'removed')
            : (string) $this->product_key?->label();
    }

    /**
     * @return BelongsTo<BumpOffer, $this>
     */
    public function bumpOffer(): BelongsTo
    {
        return $this->belongsTo(BumpOffer::class);
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
                    ->whereColumn('reversals.transaction_id', 'purchases.transaction_id')
                    ->whereColumn('reversals.item_key', 'purchases.item_key')
                    ->whereIn('reversals.action', [PurchaseAction::Refund->value, PurchaseAction::Chargeback->value]);
            });
    }
}
