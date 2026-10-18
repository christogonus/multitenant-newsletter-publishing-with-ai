<?php

namespace App\Integrations;

use App\Enums\Platform;
use Closure;

/**
 * Stops buyer-supplied addresses (such as a Ghost site URL) from reaching private networks.
 * The host must resolve only to public IP addresses, and requests never follow redirects.
 */
class OutboundUrlGuard
{
    /** @var (Closure(string): list<string>)|null */
    private static ?Closure $resolver = null;

    /**
     * @throws PlatformException
     */
    public static function assertPublic(string $url, Platform $platform): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw self::rejected($platform);
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($addresses === []) {
            throw new PlatformException(
                "We could not find {$platform->label()} at that address.",
                'Check the web address you entered, for example https://yourname.ghost.io',
            );
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw self::rejected($platform);
            }
        }
    }

    /**
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }

    private static function rejected(Platform $platform): PlatformException
    {
        return new PlatformException(
            "That {$platform->label()} address cannot be used.",
            'Use the public web address of your site, starting with https://',
            credentialsInvalid: true,
        );
    }
}
