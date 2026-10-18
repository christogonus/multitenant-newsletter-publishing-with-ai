<?php

namespace App\Support;

use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Support\Facades\URL;

/**
 * Builds links inside public pages for whichever surface served the request (subdomain, custom domain or preview).
 */
class PublicLinks
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return route(self::mode().'.'.$name, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function signed(string $name, array $parameters = [], ?int $days = null): string
    {
        return $days
            ? URL::temporarySignedRoute(self::mode().'.'.$name, now()->addDays($days), $parameters)
            : URL::signedRoute(self::mode().'.'.$name, $parameters);
    }

    /**
     * A signed referral-status link on the publication's canonical public host, for use outside a public request.
     */
    public static function referralStatusUrl(Subscriber $subscriber): string
    {
        $newsletter = $subscriber->newsletter;

        return URL::signedRoute('public.referrals', [
            'publication' => $newsletter->subdomain,
            'subscriber' => $subscriber->getKey(),
        ]);
    }

    public static function referralShareUrl(Newsletter $newsletter, Subscriber $subscriber): string
    {
        return $newsletter->publicUrl('r/'.$subscriber->referral_code);
    }

    public static function mode(): string
    {
        return (string) (request()->attributes->get('publication_mode') ?? 'public');
    }
}
