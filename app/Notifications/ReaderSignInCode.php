<?php

namespace App\Notifications;

use App\Models\Newsletter;
use App\Support\PaidReaders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code a reader types in to confirm their email before reading paid issues.
 */
class ReaderSignInCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Newsletter $newsletter, public string $code) {}

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
            ->from(config('mail.from.address'), $this->newsletter->name)
            ->subject('Your sign-in code for '.$this->newsletter->name.': '.$this->code)
            ->greeting('Your sign-in code')
            ->line('Enter this code to keep reading '.$this->newsletter->name.':')
            ->line('**'.$this->code.'**')
            ->line('It works for '.PaidReaders::CODE_MINUTES.' minutes. If you did not ask for it, you can ignore this email.');
    }
}
