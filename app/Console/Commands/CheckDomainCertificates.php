<?php

namespace App\Console\Commands;

use App\Enums\TlsStatus;
use App\Jobs\RefreshDomainCertificateJob;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps every custom domain's certificate status current: requests in progress every run,
 * working domains once a day (so a domain that stops working is reported to its owner).
 */
#[Signature('newspub:check-domain-certificates')]
#[Description('Check the https status of custom domains')]
class CheckDomainCertificates extends Command
{
    public function handle(): int
    {
        $domains = Domain::query()
            ->whereNotNull('verified_at')
            ->where(fn ($query) => $query
                ->where('tls_status', TlsStatus::Requested)
                ->orWhere(fn ($daily) => $daily
                    ->whereIn('tls_status', [TlsStatus::Active, TlsStatus::External])
                    ->where(fn ($stale) => $stale->whereNull('tls_checked_at')->orWhere('tls_checked_at', '<', now()->subDay()))))
            ->get();

        $domains->each(fn (Domain $domain) => RefreshDomainCertificateJob::dispatch($domain));

        $this->info("Checking {$domains->count()} domains.");

        return self::SUCCESS;
    }
}
