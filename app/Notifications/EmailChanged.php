<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the old inbox its account now signs in with a different email, so a takeover doesn't go unnoticed.
 */
class EmailChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmail) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' email was changed')
            ->greeting('Your email was changed')
            ->line('Your '.config('app.name').' account now signs in with '.$this->newEmail.' instead of this address.')
            ->line('Did not do this? Email '.config('newspub.support_email').' right away so we can get your account back to you.');
    }
}
