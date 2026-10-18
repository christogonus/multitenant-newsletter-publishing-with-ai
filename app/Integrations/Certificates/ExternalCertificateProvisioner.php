<?php

namespace App\Integrations\Certificates;

use Carbon\CarbonInterface;

/**
 * Used when the web server issues certificates on its own (Caddy on-demand TLS asking
 * /internal/domains/allowed) and in local development. Nothing to call; status comes from probing https.
 */
class ExternalCertificateProvisioner implements CertificateProvisioner
{
    public function manages(): bool
    {
        return false;
    }

    public function addDomain(string $hostname): void {}

    public function requestCertificate(string $hostname, string $callbackUrl): void {}

    public function removeDomain(string $hostname): void {}

    public function certificateExpiry(string $hostname): ?CarbonInterface
    {
        return null;
    }
}
