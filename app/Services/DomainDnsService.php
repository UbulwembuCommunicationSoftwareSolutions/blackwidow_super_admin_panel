<?php

namespace App\Services;

class DomainDnsService
{
    /**
     * Resolve A records for a hostname (no scheme).
     *
     * @return array{resolves: bool, ips: list<string>}
     */
    public function lookup(string $domain): array
    {
        $domain = $this->normalize($domain);

        if ($domain === '') {
            return ['resolves' => false, 'ips' => []];
        }

        try {
            $records = dns_get_record($domain, DNS_A) ?: [];
        } catch (\Throwable) {
            return ['resolves' => false, 'ips' => []];
        }

        $ips = [];
        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }

        $ips = array_values(array_unique($ips));

        return [
            'resolves' => $ips !== [],
            'ips' => $ips,
        ];
    }

    public function resolves(string $domain): bool
    {
        return $this->lookup($domain)['resolves'];
    }

    private function normalize(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;

        return rtrim(strtolower($domain), '/');
    }
}
