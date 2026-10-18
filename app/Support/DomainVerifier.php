<?php

namespace App\Support;

use App\Models\Domain;

/**
 * Checks the TXT record a customer adds to prove they own a domain (OTO 8).
 * Certificates are issued by the proxy, which asks DomainAllowedController first.
 */
class DomainVerifier
{
    public function verify(Domain $domain): bool
    {
        $records = $this->txtRecords($domain->verificationRecordName());
        $verified = in_array($domain->verificationRecordValue(), $records, true);

        $domain->forceFill([
            'verified_at' => $verified ? ($domain->verified_at ?? now()) : null,
            'last_checked_at' => now(),
        ])->save();

        return $verified;
    }

    /**
     * @return list<string>
     */
    protected function txtRecords(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record) => isset($record['txt']) ? trim($record['txt']) : null,
            $records,
        )));
    }
}
