<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Buyers who sign in with the default password never see the set-password form's terms checkbox.
 * The sign-in page says signing in accepts the terms, so the first sign-in records it.
 */
class RecordTermsAcceptance
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $event->user->terms_accepted_at === null) {
            $event->user->forceFill(['terms_accepted_at' => now()])->save();
        }
    }
}
