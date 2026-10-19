<?php

namespace App\Models;

use App\Enums\EarningSource;
use Database\Factories\EarningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a newsletter brought in (a sponsor payment, a month of paid subscriptions, a promotion), logged by its owner.
 */
#[Fillable(['newsletter_id', 'source', 'description', 'amount', 'earned_on'])]
class Earning extends Model
{
    /** @use HasFactory<EarningFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => EarningSource::class,
            'amount' => 'decimal:2',
            'earned_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }
}
