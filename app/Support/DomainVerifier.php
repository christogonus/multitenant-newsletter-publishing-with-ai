<?php

namespace App\Support;

use App\Models\Domain;

/**
 * Basic checks before a customer domain becomes a publication's primary address and gets a certificate (OTO 8):
 *  - ownership: the TXT record with the domain's token is present;
 *  - points to us: the address resolves to our server (A record to NEWSPUB_SERVER_IP, or a CNAME to the public root);
 *  - for a bare domain, www also points to us (it forwards to the bare domain).
 * Each result is saved on the domain so the owner can see which step is still missing.
 */
class DomainVerifier
{
    public function verify(Domain $domain): bool
    {
        $checks = [
            'ownership' => in_array($domain->verificationRecordValue(), $this->txtRecords($domain->verificationRecordName()), true),
            'points_to_us' => $this->pointsToUs($domain->hostname, allowCname: ! $domain->is_apex),
        ];

        if ($domain->is_apex) {
            $checks['www_points_to_us'] = $this->pointsToUs((string) $domain->wwwHostname(), allowCname: true);
        }

        // Another publication already proved ownership of this hostname.
        $takenByOther = Domain::query()
            ->where('hostname', $domain->hostname)
            ->whereNotNull('verified_at')
            ->whereKeyNot($domain->id)
            ->exists();

        $verified = ! $takenByOther && ! in_array(false, $checks, true);

        $domain->forceFill([
            'dns_checks' => $checks,
            'verified_at' => $verified ? ($domain->verified_at ?? now()) : null,
            'last_checked_at' => now(),
        ])->save();

        // The proven owner wins: competing unverified claims on the same hostname are removed.
        if ($verified) {
            Domain::query()->where('hostname', $domain->hostname)->whereNull('verified_at')->whereKeyNot($domain->id)->delete();
        }

        return $verified;
    }

    /**
     * Our server's addresses: NEWSPUB_SERVER_IP plus whatever the public root currently resolves to.
     *
     * @return list<string>
     */
    public function serverAddresses(): array
    {
        return array_values(array_unique([
            ...config('newspub.certificates.server_ips'),
            ...$this->aRecords((string) config('newspub.public_root')),
        ]));
    }

    private function pointsToUs(string $hostname, bool $allowCname): bool
    {
        $publicRoot = strtolower(rtrim((string) config('newspub.public_root'), '.'));

        if ($allowCname && in_array($publicRoot, array_map(fn (string $target) => strtolower(rtrim($target, '.')), $this->cnameRecords($hostname)), true)) {
            return true;
        }

        return array_intersect($this->aRecords($hostname), $this->serverAddresses()) !== [];
    }

    /**
     * @return list<string>
     */
    protected function txtRecords(string $name): array
    {
        return $this->lookup($name, DNS_TXT, 'txt');
    }

    /**
     * @return list<string>
     */
    protected function aRecords(string $name): array
    {
        return $name === '' ? [] : $this->lookup($name, DNS_A, 'ip');
    }

    /**
     * @return list<string>
     */
    protected function cnameRecords(string $name): array
    {
        return $this->lookup($name, DNS_CNAME, 'target');
    }

    /**
     * @return list<string>
     */
    private function lookup(string $name, int $type, string $field): array
    {
        $records = @dns_get_record($name, $type) ?: [];

        return array_values(array_filter(array_map(fn (array $record) => isset($record[$field]) ? trim((string) $record[$field]) : null, $records)));
    }
}
