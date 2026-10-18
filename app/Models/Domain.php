<?php

namespace App\Models;

use App\Enums\TlsStatus;
use App\Jobs\RemoveDomainTenantJob;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'newsletter_id', 'hostname', 'verification_token', 'verified_at', 'tls_status', 'last_checked_at', 'tls_error',
    'tls_attempts', 'tls_requested_at', 'tls_issued_at', 'tls_expires_at', 'tls_checked_at', 'tenant_created',
])]
class Domain extends Model
{
    /**
     * Minutes to wait for a requested certificate before reporting it as failed.
     */
    public const CERTIFICATE_TIMEOUT_MINUTES = 30;

    /**
     * Owners may ask for a new certificate this often per hour.
     */
    public const MAX_REQUESTS_PER_HOUR = 5;

    protected static function booted(): void
    {
        // A disconnected domain is taken off the web server too, along with its certificate.
        static::deleted(function (Domain $domain) {
            if ($domain->tenant_created) {
                RemoveDomainTenantJob::dispatch($domain->hostname);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'tls_status' => TlsStatus::class,
            'tls_requested_at' => 'datetime',
            'tls_issued_at' => 'datetime',
            'tls_expires_at' => 'datetime',
            'tls_checked_at' => 'datetime',
            'tenant_created' => 'boolean',
        ];
    }

    /**
     * Whether the owner may ask for a certificate again: after a failure, or when a request has been stuck.
     */
    public function canRequestCertificate(): bool
    {
        if (! $this->verified_at || $this->tls_status === TlsStatus::External) {
            return false;
        }

        return $this->tls_status === TlsStatus::Failed
            || $this->tls_status === TlsStatus::Pending
            || ($this->tls_status === TlsStatus::Requested && $this->tls_requested_at?->lt(now()->subMinutes(15)));
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * The TXT record name the owner adds to prove they own the domain.
     */
    public function verificationRecordName(): string
    {
        return '_newspub.'.$this->hostname;
    }

    public function verificationRecordValue(): string
    {
        return 'newspub-verify='.$this->verification_token;
    }
}
