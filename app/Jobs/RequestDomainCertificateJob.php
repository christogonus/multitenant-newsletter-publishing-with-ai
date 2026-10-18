<?php

namespace App\Jobs;

use App\Enums\TlsStatus;
use App\Integrations\Certificates\CertificateException;
use App\Integrations\Certificates\CertificateProvisioner;
use App\Models\Domain;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;

/**
 * Adds a verified custom domain to the web server and asks for its https certificate.
 */
class RequestDomainCertificateJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public int $uniqueFor = 300;

    public function __construct(public Domain $domain)
    {
        $this->onQueue('integrations');
    }

    public function uniqueId(): string
    {
        return (string) $this->domain->id;
    }

    public function handle(CertificateProvisioner $provisioner): void
    {
        $domain = $this->domain->fresh();

        if (! $domain?->verified_at) {
            return;
        }

        if (! $provisioner->manages()) {
            $domain->update(['tls_status' => TlsStatus::External, 'tls_error' => null]);
            RefreshDomainCertificateJob::dispatch($domain)->delay(now()->addMinute());

            return;
        }

        try {
            $provisioner->addDomain($domain->hostname);
            $domain->update(['tenant_created' => true]);

            $provisioner->requestCertificate($domain->hostname, self::callbackUrl($domain));
        } catch (CertificateException $exception) {
            if ($exception->retryable && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $domain->update(['tls_status' => TlsStatus::Failed, 'tls_error' => $exception->getMessage(), 'tls_checked_at' => now()]);

            return;
        }

        $domain->update([
            'tls_status' => TlsStatus::Requested,
            'tls_error' => null,
            'tls_requested_at' => now(),
            'tls_attempts' => $domain->tls_attempts + 1,
        ]);

        // Ploi calls the callback when it is done; this is the fallback if that call never arrives.
        RefreshDomainCertificateJob::dispatch($domain)->delay(now()->addMinutes(3));
    }

    /**
     * The signed address the hosting service calls when the certificate step has finished.
     */
    public static function callbackUrl(Domain $domain): string
    {
        return URL::temporarySignedRoute('internal.domains.certificate-callback', now()->addDays(2), ['domain' => $domain->id]);
    }
}
