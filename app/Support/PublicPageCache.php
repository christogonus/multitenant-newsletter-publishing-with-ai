<?php

namespace App\Support;

use App\Models\Newsletter;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches rendered public pages per publication. Publishing or editing bumps the version, which busts every page at once.
 */
class PublicPageCache
{
    public const TTL_MINUTES = 30;

    /**
     * @param  Closure(): string  $render
     */
    public static function remember(Newsletter $newsletter, string $page, Closure $render): string
    {
        return Cache::remember(
            'public-page:'.$newsletter->id.':'.self::version($newsletter).':'.md5($page),
            now()->addMinutes(self::TTL_MINUTES),
            $render,
        );
    }

    public static function bust(Newsletter $newsletter): void
    {
        Cache::forever(self::versionKey($newsletter), (string) microtime(true));
    }

    private static function version(Newsletter $newsletter): string
    {
        return (string) Cache::rememberForever(self::versionKey($newsletter), fn () => (string) microtime(true));
    }

    private static function versionKey(Newsletter $newsletter): string
    {
        return 'public-page-version:'.$newsletter->id;
    }
}
