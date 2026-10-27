<?php

namespace App\Models;

use App\Enums\Product;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email an admin sends to a group of customers: everyone, or the owners of chosen products and order bumps.
 * Suspended accounts and anyone who unsubscribed never get one; demo accounts only when the admin asks.
 */
#[Fillable(['sent_by', 'from_name', 'from_email', 'subject', 'body', 'button_label', 'button_url', 'audience', 'recipients_count', 'sent_at'])]
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * The accounts an audience reaches. Items are product keys ("oto2_silver") or order bumps ("bump:12"); owning
     * Gold counts as owning Silver, as it does everywhere else.
     *
     * @param  array{mode: string, items?: list<string>, exclude_items?: list<string>, include_demo?: bool}  $audience
     * @return Builder<User>
     */
    public static function recipientsFor(array $audience): Builder
    {
        return User::query()
            ->whereNull('suspended_at')
            ->whereNull('emails_unsubscribed_at')
            ->unless($audience['include_demo'] ?? false, fn (Builder $query) => $query->where('is_demo', false))
            ->when($audience['mode'] === 'owners', fn (Builder $query) => $query->whereHas('purchases', fn (Builder $purchases) => self::owningAny($purchases, $audience['items'] ?? [])))
            ->when(($audience['exclude_items'] ?? []) !== [], fn (Builder $query) => $query->whereDoesntHave('purchases', fn (Builder $purchases) => self::owningAny($purchases, $audience['exclude_items'])));
    }

    /**
     * @return Builder<User>
     */
    public function recipients(): Builder
    {
        return self::recipientsFor($this->audience);
    }

    /**
     * Limits standing purchases to ones that grant any of the items.
     *
     * @param  Builder<Purchase>  $purchases
     * @param  list<string>  $items
     */
    private static function owningAny(Builder $purchases, array $items): void
    {
        $bumpIds = [];
        $productKeys = [];

        foreach ($items as $item) {
            if (str_starts_with($item, 'bump:')) {
                $bumpIds[] = (int) substr($item, 5);
            } elseif ($product = Product::tryFrom($item)) {
                array_push($productKeys, ...array_map(fn (Product $granting) => $granting->value, $product->grantedBy()));
            }
        }

        $purchases->standing()->where(function (Builder $owning) use ($bumpIds, $productKeys) {
            $owning->whereIn('product_key', $productKeys ?: [''])->orWhereIn('bump_offer_id', $bumpIds ?: [0]);
        });
    }
}
