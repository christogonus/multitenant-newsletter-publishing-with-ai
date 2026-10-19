<?php

namespace App\Models;

use App\Enums\PaidPlan;
use App\Enums\PaymentClaimStatus;
use Database\Factories\PaymentClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's "I've paid" for a manual payment (OTO 4). The owner checks it and activates or declines.
 */
#[Fillable(['newsletter_id', 'email', 'plan', 'reference', 'status', 'resolved_at'])]
class PaymentClaim extends Model
{
    /** @use HasFactory<PaymentClaimFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'plan' => PaidPlan::class,
            'status' => PaymentClaimStatus::class,
            'resolved_at' => 'datetime',
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
     * @param  Builder<PaymentClaim>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PaymentClaimStatus::Pending);
    }
}
