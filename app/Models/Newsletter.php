<?php

namespace App\Models;

use App\Enums\Frequency;
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

    public function activeSubscriberCount(): int
    {
        return $this->subscribers()->whereNull('unsubscribed_at')->count();
    }
}
