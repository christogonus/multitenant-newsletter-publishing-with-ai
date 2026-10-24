<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\PaidPlan;
use App\Enums\Product;
use Database\Factories\NewsletterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id', 'name', 'tagline', 'niche', 'audience', 'frequency', 'subdomain', 'voice', 'template',
    'branding', 'reward_tiers', 'auto_publish', 'kit_key', 'topic_pipeline', 'moderated_at', 'moderation_reason',
    'paid_enabled', 'paid_monthly_cents', 'paid_yearly_cents', 'paid_currency', 'paid_perks',
    'paid_manual_enabled', 'paid_manual_instructions', 'paid_manual_contact',
])]
class Newsletter extends Model
{
    /** @use HasFactory<NewsletterFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Delete the custom domain through the model (not the database cascade) so it leaves the web server too.
        static::deleting(function (Newsletter $newsletter) {
            $newsletter->domain?->delete();
        });

        // Uploaded logos and issue pictures go with the publication.
        static::deleted(function (Newsletter $newsletter) {
            Storage::disk('public')->deleteDirectory('logos/'.$newsletter->id);
            Storage::disk('public')->deleteDirectory('issue-images/'.$newsletter->id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'frequency' => Frequency::class,
            'branding' => 'array',
            'reward_tiers' => 'array',
            'topic_pipeline' => 'array',
            'auto_publish' => 'boolean',
            'moderated_at' => 'datetime',
            'link_shared_at' => 'datetime',
            'checklist_dismissed_at' => 'datetime',
            'paid_enabled' => 'boolean',
            'paid_manual_enabled' => 'boolean',
            'paid_monthly_cents' => 'integer',
            'paid_yearly_cents' => 'integer',
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
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * @return HasMany<Subscriber, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /**
     * @return HasMany<PlatformConnection, $this>
     */
    public function platformConnections(): HasMany
    {
        return $this->hasMany(PlatformConnection::class);
    }

    /**
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * @return HasMany<PaymentClaim, $this>
     */
    public function paymentClaims(): HasMany
    {
        return $this->hasMany(PaymentClaim::class);
    }

    /**
     * @return HasMany<LeadPage, $this>
     */
    public function leadPages(): HasMany
    {
        return $this->hasMany(LeadPage::class);
    }

    /**
     * @return HasMany<CalendarItem, $this>
     */
    public function calendarItems(): HasMany
    {
        return $this->hasMany(CalendarItem::class);
    }

    /**
     * @return HasMany<Earning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasOne<MediaKit, $this>
     */
    public function mediaKit(): HasOne
    {
        return $this->hasOne(MediaKit::class);
    }

    /**
     * @return HasOne<Domain, $this>
     */
    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    /**
     * @return HasOne<NetworkListing, $this>
     */
    public function networkListing(): HasOne
    {
        return $this->hasOne(NetworkListing::class);
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_newsletters')->withTimestamps();
    }

    /**
     * Publications that have not been taken offline by moderation.
     *
     * @param  Builder<Newsletter>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('moderated_at');
    }

    /**
     * Visible publications whose owner is not suspended: the ones that may appear on other newsletters and in the network.
     *
     * @param  Builder<Newsletter>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->visible()->whereHas('user', fn (Builder $users) => $users->whereNull('suspended_at'));
    }

    /**
     * The owner's account is suspended, so the publication is on hold.
     */
    public function isOnHold(): bool
    {
        return (bool) $this->user?->isSuspended();
    }

    public function isModerated(): bool
    {
        return $this->moderated_at !== null;
    }

    /**
     * The public address: the verified custom domain if any, otherwise the subdomain.
     */
    public function publicUrl(string $path = ''): string
    {
        $host = $this->domain?->verified_at
            ? $this->domain->hostname
            : $this->subdomain.'.'.config('newspub.public_root');

        return config('newspub.public_scheme').'://'.$host.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * @return array{logo_path: ?string, primary_color: string, accent_color: string, font: string}
     */
    public function brand(): array
    {
        return array_merge([
            'logo_path' => null,
            'primary_color' => '#111827',
            'accent_color' => '#4F46E5',
            'font' => 'system',
        ], $this->branding ?? []);
    }

    /**
     * Whether issues marked for paying readers are locked: the owner has OTO 4 and has turned paid subscriptions on.
     */
    public function hasPaidSubscriptions(): bool
    {
        return $this->paid_enabled && $this->user->owns(Product::Oto4Sponsor);
    }

    /**
     * Whether readers can pay the owner directly (bank transfer, crypto and so on) and email them to be let in.
     */
    public function acceptsManualPayments(): bool
    {
        return $this->paid_manual_enabled && filled($this->paid_manual_instructions) && filled($this->paid_manual_contact);
    }

    /**
     * The price of a plan in cents, or null when the owner does not offer it.
     */
    public function paidPriceCents(PaidPlan $plan): ?int
    {
        $cents = $plan === PaidPlan::Monthly ? $this->paid_monthly_cents : $this->paid_yearly_cents;

        return $cents > 0 ? $cents : null;
    }

    /**
     * A price for readers, such as "$5.00".
     */
    public function formatPaidPrice(int $cents): string
    {
        $symbols = ['USD' => '$', 'CAD' => 'CA$', 'AUD' => 'A$', 'EUR' => '€', 'GBP' => '£'];
        $currency = strtoupper($this->paid_currency ?: 'USD');

        return ($symbols[$currency] ?? $currency.' ').number_format($cents / 100, 2);
    }

    public function activeSubscriberCount(): int
    {
        return $this->subscribers()->whereNull('unsubscribed_at')->count();
    }
}
