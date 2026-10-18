<?php

namespace App\Jobs;

use App\Integrations\Certificates\CertificateProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Removes a custom domain (and its certificate) from the web server after the owner disconnects it.
 * Takes the hostname, not the model, because the domain row is already gone.
 */
class RemoveDomainTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public string $hostname)
    {
        $this->onQueue('integrations');
    }

    public function handle(CertificateProvisioner $provisioner): void
    {
        if ($provisioner->manages()) {
            $provisioner->removeDomain($this->hostname);
        }
    }
}
