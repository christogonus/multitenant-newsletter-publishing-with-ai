<?php

namespace App\Actions;

use App\Jobs\SyncSubscribersJob;
use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Support\Str;

/**
 * Stores a reader with consent time and IP. The app never emails them; connected platforms run double opt-in.
 */
class SubscribeReader
{
    public function handle(
        Newsletter $newsletter,
        string $email,
        ?string $ip,
        ?string $referralCode = null,
        string $source = 'hosted_page',
        ?int $leadPageId = null,
        ?string $name = null,
    ): Subscriber {
        $email = Str::lower(trim($email));

        $subscriber = Subscriber::query()->whereBelongsTo($newsletter)->where('email', $email)->first();

        // An existing reader is returned untouched. A public form can never clear an opt-out or rewrite consent,
        // and callers only reveal reader-specific links when $subscriber->wasRecentlyCreated is true.
        if ($subscriber) {
            return $subscriber;
        }

        $referrer = $referralCode
            ? Subscriber::query()->whereBelongsTo($newsletter)->where('referral_code', $referralCode)->active()->first()
            : null;

        $subscriber = $newsletter->subscribers()->create([
            'email' => $email,
            'name' => $name ? Str::limit(trim($name), 120, '') : null,
            'source' => $source,
            'consent_at' => now(),
            'consent_ip' => $ip,
            'referred_by_id' => $referrer?->id,
            'lead_page_id' => $leadPageId,
        ]);

        $this->sync($newsletter);

        return $subscriber;
    }

    private function sync(Newsletter $newsletter): void
    {
        if ($newsletter->platformConnections()->where('status', 'connected')->exists()) {
            SyncSubscribersJob::dispatch($newsletter)->delay(now()->addSeconds(30));
        }
    }
}
