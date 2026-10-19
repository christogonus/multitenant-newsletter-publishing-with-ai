<?php

namespace App\Models;

use App\Enums\PaidPlan;
use App\Enums\PaymentProvider;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'newsletter_id', 'email', 'name', 'source', 'consent_at', 'consent_ip', 'referral_code', 'referred_by_id',
    'lead_page_id', 'synced_at', 'unsubscribed_at', 'paid_until', 'paid_via', 'paid_plan', 'payment_reference',
])]
class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber) {
            $subscriber->referral_code ??= Str::lower(Str::random(12));
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'synced_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'paid_until' => 'datetime',
            'paid_via' => PaymentProvider::class,
            'paid_plan' => PaidPlan::class,
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
     * @return BelongsTo<Subscriber, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'referred_by_id');
    }

    /**
     * @return HasMany<Subscriber, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Subscriber::class, 'referred_by_id');
    }

    /**
     * @return BelongsTo<LeadPage, $this>
     */
    public function leadPage(): BelongsTo
    {
        return $this->belongsTo(LeadPage::class);
    }

    public function hasPaidAccess(): bool
    {
        return $this->paid_until !== null && $this->paid_until->isFuture();
    }

    /**
     * Readers whose paid access has not run out.
     *
     * @param  Builder<Subscriber>  $query
     */
    public function scopePaying(Builder $query): void
    {
        $query->where('paid_until', '>', now());
    }

    /**
     * @param  Builder<Subscriber>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }
}
