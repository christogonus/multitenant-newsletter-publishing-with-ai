<?php

namespace App\Notifications;

use App\Livewire\Account\ChangeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code that proves a buyer owns the inbox they want to sign in with.
 */
class EmailChangeCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

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
            ->subject('Your '.config('app.name').' confirmation code: '.$this->code)
            ->greeting('Confirm your new email')
            ->line('Someone asked to use this address to sign in to a '.config('app.name').' account. Type this code on the Account page to finish:')
            ->line('**'.$this->code.'**')
            ->line('The code works for '.ChangeEmail::CODE_MINUTES.' minutes.')
            ->line('Did not ask for this? Ignore this email and nothing changes.');
    }
}
