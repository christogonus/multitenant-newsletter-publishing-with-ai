<?php

namespace App\Models;

use App\Enums\SwapStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requester_listing_id', 'recipient_listing_id', 'status', 'message', 'responded_at'])]
class NetworkSwap extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SwapStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NetworkListing, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(NetworkListing::class, 'requester_listing_id');
    }

    /**
     * @return BelongsTo<NetworkListing, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NetworkListing::class, 'recipient_listing_id');
    }
}
