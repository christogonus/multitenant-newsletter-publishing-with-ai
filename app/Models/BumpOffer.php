<?php

namespace App\Models;

use App\Enums\Product;
use Database\Factories\BumpOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order bump sold on a product's checkout: downloadable files (PDFs, images), a built-in product to unlock, or both.
 * LaunchpadJV only says "the bump was taken", so each product has at most one active bump at a time.
 */
#[Fillable(['product_key', 'name', 'description', 'price', 'unlocks_product', 'is_active'])]
class BumpOffer extends Model
{
    /** @use HasFactory<BumpOfferFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'product_key' => Product::class,
            'unlocks_product' => Product::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BumpOfferFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(BumpOfferFile::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * The bump a sale of this product delivers when the buyer ticked the bump box.
     */
    public static function activeFor(Product $product): ?self
    {
        return self::query()->where('product_key', $product)->where('is_active', true)->latest('id')->first();
    }

    /**
     * Makes this the product's bump, switching off any other bump on the same product.
     */
    public function activate(): void
    {
        self::query()->where('product_key', $this->product_key)->whereKeyNot($this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Whether the buyer owns this bump through a sale that has not been refunded.
     */
    public function isOwnedBy(User $user): bool
    {
        return $user->purchases()->standing()->where('bump_offer_id', $this->id)->exists();
    }
}
