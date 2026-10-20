<?php

namespace App\Models;

use App\Enums\Product;
use App\Support\Entitlements;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Pennant\Concerns\HasFeatures;

#[Fillable(['name', 'email', 'password', 'terms_accepted_at', 'current_newsletter_id', 'reseller_slug', 'reseller_brand'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasFeatures, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_demo' => 'boolean',
            'suspended_at' => 'datetime',
            'uses_default_password' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<Newsletter, $this>
     */
    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function currentNewsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class, 'current_newsletter_id');
    }

    /**
     * @return HasMany<AiUsage, $this>
     */
    public function aiUsage(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<LicenseCode, $this>
     */
    public function licenseCodes(): HasMany
    {
        return $this->hasMany(LicenseCode::class, 'reseller_id');
    }

    /**
     * Suspended by an admin: the dashboard and every newsletter this account runs are on hold.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * The buyer has working login details: a password they set from their inbox, or the published default.
     * Accounts without either (for example ones made with a reseller code) still need a set-password link.
     */
    public function hasLoginDetails(): bool
    {
        return $this->email_verified_at !== null || $this->uses_default_password;
    }

    /**
     * Whether the ledger currently grants the given product (via Pennant).
     */
    public function owns(Product $product): bool
    {
        return app(Entitlements::class)->has($this, $product);
    }

    /**
     * The publication the dashboard is working on: the selected one, or the first.
     */
    public function activeNewsletter(): ?Newsletter
    {
        $selected = $this->current_newsletter_id
            ? $this->newsletters()->whereKey($this->current_newsletter_id)->first()
            : null;

        return $selected ?? $this->newsletters()->oldest('id')->first();
    }
}
