<?php

namespace App\Integrations\Certificates;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Ploi tenants: each verified custom domain becomes a tenant of the app's Ploi site, with its own
 * NGINX server block and Let's Encrypt certificate. https://developers.ploi.io/tenants
 */
class PloiCertificateProvisioner implements CertificateProvisioner
{
    public const BASE_URL = 'https://ploi.io/api';

    public function __construct(
        private string $token,
        private string $serverId,
        private string $siteId,
    ) {}

    public function manages(): bool
    {
        return true;
    }

    public function addDomain(string $hostname): void
    {
        $tenants = (array) $this->send(fn (PendingRequest $http) => $http->get($this->sitePath('tenants')))->json('data.tenants', []);

        if (in_array($hostname, array_map('strtolower', $tenants), true)) {
            return;
        }

        $this->send(fn (PendingRequest $http) => $http->post($this->sitePath('tenants'), ['tenants' => [$hostname]]));
    }

    public function requestCertificate(string $hostname, string $callbackUrl, array $alsoCover = []): void
    {
        $this->send(fn (PendingRequest $http) => $http->post($this->sitePath('tenants/'.rawurlencode($hostname).'/request-certificate'), [
            // Including www of a bare domain also makes Ploi redirect www to the bare domain.
            'domains' => implode(',', array_values(array_unique([$hostname, ...$alsoCover]))),
            'webhook' => $callbackUrl,
        ]));
    }

    public function removeDomain(string $hostname): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete($this->sitePath('tenants/'.rawurlencode($hostname))), acceptable: [404]);
    }

    /**
     * Ploi's certificate list is not documented to include tenant certificates, so this is best effort.
     */
    public function certificateExpiry(string $hostname): ?CarbonInterface
    {
        try {
            $page = 1;

            do {
                $response = $this->send(fn (PendingRequest $http) => $http->get($this->sitePath('certificates'), ['page' => $page]));

                foreach ((array) $response->json('data', []) as $certificate) {
                    $domains = array_map('trim', explode(',', strtolower((string) ($certificate['domain'] ?? ''))));

                    if (in_array($hostname, $domains, true) && ! empty($certificate['expires_at'])) {
                        return Carbon::parse($certificate['expires_at']);
                    }
                }

                $page++;
            } while ($page <= (int) $response->json('meta.last_page', 1) && $page <= 20);
        } catch (CertificateException) {
            return null;
        }

        return null;
    }

    private function sitePath(string $path): string
    {
        return "servers/{$this->serverId}/sites/{$this->siteId}/{$path}";
    }

    /**
     * @param  \Closure(PendingRequest): Response  $request
     * @param  list<int>  $acceptable
     *
     * @throws CertificateException
     */
    private function send(\Closure $request, array $acceptable = []): Response
    {
        try {
            $response = $request(
                Http::baseUrl(self::BASE_URL)
                    ->withToken($this->token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->connectTimeout(10)
            );
        } catch (ConnectionException $exception) {
            throw new CertificateException('We could not reach our hosting service. We will try again shortly.', retryable: true, previous: $exception);
        }

        if ($response->successful() || in_array($response->status(), $acceptable, true)) {
            return $response;
        }

        throw match (true) {
            $response->status() === 422 => new CertificateException(
                'The certificate could not be set up: '.$this->reason($response).' Check that your address points to us, then try again.'
            ),
            $response->status() === 429, $response->serverError() => new CertificateException(
                'Our hosting service is busy. We will try again shortly.',
                retryable: true,
            ),
            default => new CertificateException('The certificate could not be set up (code '.$response->status().'). Our team has been told.'),
        };
    }

    private function reason(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('errors.domains.0') ?? $response->json('errors.tenants.0') ?? 'the address was not accepted.';

        return rtrim(mb_substr(strip_tags((string) $message), 0, 240), '.').'.';
    }
}
