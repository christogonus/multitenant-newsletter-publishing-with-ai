<?php

namespace App\Notifications;

use App\Livewire\Account\ChooseOwnPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code that proves a buyer owns their inbox before they replace the shared starting password.
 */
class PasswordChangeCode extends Notification implements ShouldQueue
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
            ->greeting('Confirm it is you')
            ->line('Someone signed in to your '.config('app.name').' account asked to choose a new password. Type this code on the Account page to finish:')
            ->line('**'.$this->code.'**')
            ->line('The code works for '.ChooseOwnPassword::CODE_MINUTES.' minutes.')
            ->line('Did not ask for this? Someone may know your email and the starting password. Sign in and change your password now: until you do, nobody else can change it, because the code only comes to you.');
    }
}
