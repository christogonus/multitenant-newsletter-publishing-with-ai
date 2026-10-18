<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Small helpers for customer domains: tidy what the owner typed, and tell a bare domain
 * (theirsite.com, theirsite.co.uk) from a subdomain (news.theirsite.com).
 */
class Hostnames
{
    /**
     * Endings that take two labels, so theirsite.co.uk counts as a bare domain.
     */
    private const SECOND_LEVEL_SUFFIXES = [
        'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'ac.uk', 'gov.uk',
        'com.au', 'net.au', 'org.au', 'co.nz', 'org.nz', 'net.nz',
        'co.za', 'org.za', 'com.ng', 'org.ng', 'com.br', 'net.br', 'com.mx',
        'co.in', 'net.in', 'org.in', 'firm.in', 'co.jp', 'ne.jp', 'or.jp',
        'com.sg', 'com.my', 'com.ph', 'com.pk', 'com.tr', 'com.ar', 'com.co',
        'co.ke', 'co.ug', 'co.tz', 'com.gh', 'co.il', 'co.kr', 'com.cn', 'com.hk', 'com.tw',
    ];

    /**
     * Lower-case, without scheme, path, port, trailing dot or a leading "www.".
     */
    public static function normalize(string $input): string
    {
        $host = Str::lower(trim($input));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = (string) Str::before(Str::before(Str::before($host, '/'), '?'), '#');
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = rtrim(trim($host), '.');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * The registrable domain: theirsite.com for news.theirsite.com, theirsite.co.uk for blog.theirsite.co.uk.
     */
    public static function registrable(string $host): string
    {
        $labels = explode('.', $host);
        $take = in_array(implode('.', array_slice($labels, -2)), self::SECOND_LEVEL_SUFFIXES, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    public static function isApex(string $host): bool
    {
        return self::registrable($host) === $host;
    }

    /**
     * The record name to type at the domain registrar: "@" for a bare domain, otherwise the part before
     * the registrable domain ("news" for news.theirsite.com).
     */
    public static function recordName(string $host, string $prefix = ''): string
    {
        $relative = self::isApex($host) ? '' : substr($host, 0, -strlen(self::registrable($host)) - 1);
        $name = trim($prefix.($prefix !== '' && $relative !== '' ? '.' : '').$relative, '.');

        return $name === '' ? '@' : $name;
    }
}
