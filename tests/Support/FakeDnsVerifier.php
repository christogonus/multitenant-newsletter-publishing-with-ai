<?php

namespace Tests\Support;

use App\Support\DomainVerifier;

/**
 * A DomainVerifier that answers from in-memory records instead of real DNS.
 */
class FakeDnsVerifier extends DomainVerifier
{
    /**
     * @param  array<string, list<string>>  $txt
     * @param  array<string, list<string>>  $a
     * @param  array<string, list<string>>  $cname
     */
    public function __construct(
        public array $txt = [],
        public array $a = [],
        public array $cname = [],
    ) {}

    protected function txtRecords(string $name): array
    {
        return $this->txt[$name] ?? [];
    }

    protected function aRecords(string $name): array
    {
        return $this->a[$name] ?? [];
    }

    protected function cnameRecords(string $name): array
    {
        return $this->cname[$name] ?? [];
    }
}
