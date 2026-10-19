<?php

namespace App\Models;

use App\Enums\SwapStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['newsletter_id', 'category', 'pitch', 'audience_size', 'is_seeded', 'is_listed'])]
class NetworkListing extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_seeded' => 'boolean',
            'is_listed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * @return HasMany<NetworkSwap, $this>
     */
    public function sentSwaps(): HasMany
    {
        return $this->hasMany(NetworkSwap::class, 'requester_listing_id');
    }

    /**
     * @return HasMany<NetworkSwap, $this>
     */
    public function receivedSwaps(): HasMany
    {
        return $this->hasMany(NetworkSwap::class, 'recipient_listing_id');
    }

    /**
     * Listings this one has an accepted swap with, in either direction.
     *
     * @return Collection<int, NetworkListing>
     */
    public function partners(): Collection
    {
        $sent = $this->sentSwaps()->where('status', SwapStatus::Accepted)->with('recipient.newsletter.domain', 'recipient.newsletter.user')->get()->pluck('recipient');
        $received = $this->receivedSwaps()->where('status', SwapStatus::Accepted)->with('requester.newsletter.domain', 'requester.newsletter.user')->get()->pluck('requester');

        return $sent->merge($received)->unique('id')->values();
    }
}
