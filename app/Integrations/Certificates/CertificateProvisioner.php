<?php

namespace App\Integrations\Certificates;

use Carbon\CarbonInterface;

/**
 * Puts a verified custom domain onto the web server and gets it an https certificate (OTO 8).
 * Laravel never touches certificates itself; the server tool (Ploi tenants, Caddy) does.
 */
interface CertificateProvisioner
{
    /**
     * Whether this provisioner manages certificates. When false, the server handles them on its own
     * (for example Caddy on-demand TLS) and the app only answers the "is this domain allowed?" check.
     */
    public function manages(): bool;

    /**
     * Adds the hostname to the server so requests for it reach the app.
     *
     * @throws CertificateException
     */
    public function addDomain(string $hostname): void;

    /**
     * Asks for a certificate. The provisioner calls $callbackUrl when it has finished.
     *
     * @throws CertificateException
     */
    public function requestCertificate(string $hostname, string $callbackUrl): void;

    /**
     * Removes the hostname (and its certificates) from the server. Missing domains are fine.
     *
     * @throws CertificateException
     */
    public function removeDomain(string $hostname): void;

    /**
     * When the provisioner reports the hostname's certificate expiry, return it.
     */
    public function certificateExpiry(string $hostname): ?CarbonInterface;
}
