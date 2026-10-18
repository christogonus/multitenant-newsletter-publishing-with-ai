<?php

namespace App\Jobs;

use App\Enums\TlsStatus;
use App\Integrations\Certificates\CertificateProvisioner;
use App\Integrations\Certificates\HttpsProbe;
use App\Models\Domain;
use App\Support\PublicPageCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Works out a custom domain's certificate status by visiting https://{hostname} for real.
 */
class RefreshDomainCertificateJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Domain $domain)
    {
        $this->onQueue('integrations');
    }

    public function handle(HttpsProbe $probe, CertificateProvisioner $provisioner): void
    {
        $domain = $this->domain->fresh();

        if (! $domain?->verified_at || $domain->tls_status === TlsStatus::Pending) {
            return;
        }

        if ($probe->isSecure($domain->hostname)) {
            $becameActive = $domain->tls_status !== TlsStatus::Active;

            $domain->update([
                'tls_status' => $domain->tls_status === TlsStatus::External ? TlsStatus::External : TlsStatus::Active,
                'tls_error' => null,
                'tls_issued_at' => $domain->tls_issued_at ?? now(),
                'tls_expires_at' => $provisioner->certificateExpiry($domain->hostname) ?? $domain->tls_expires_at,
                'tls_checked_at' => now(),
            ]);

            if ($becameActive) {
                PublicPageCache::bust($domain->newsletter);
            }

            return;
        }

        $domain->update(['tls_checked_at' => now(), ...$this->failure($domain)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(Domain $domain): array
    {
        return match (true) {
            $domain->tls_status === TlsStatus::Requested && $domain->tls_requested_at?->lt(now()->subMinutes(Domain::CERTIFICATE_TIMEOUT_MINUTES)) => [
                'tls_status' => TlsStatus::Failed,
                'tls_error' => 'The secure certificate was not issued in time. This usually means your address does not point to us yet. Check the settings where you bought your domain, wait a few minutes, then press "Request again".',
            ],
            $domain->tls_status === TlsStatus::Active => [
                'tls_status' => TlsStatus::Failed,
                'tls_error' => 'Your address stopped opening securely. If you changed the settings where you bought your domain, point it back to us, then press "Request again".',
            ],
            $domain->tls_status === TlsStatus::External => [
                'tls_error' => 'Your address does not open securely yet. It can take a few minutes after you connect it.',
            ],
            default => [],
        };
    }
}
