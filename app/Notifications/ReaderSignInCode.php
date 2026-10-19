<?php

namespace App\Notifications;

use App\Models\Newsletter;
use App\Support\PaidReaders;
use App\Support\Theme;
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
        $message = (new MailMessage)
            ->from(config('mail.from.address'), $this->newsletter->name)
            ->subject('Your sign-in code for '.$this->newsletter->name.': '.$this->code)
            ->view(['email.reader-sign-in-code', 'email.reader-sign-in-code-text'], [
                'newsletter' => $this->newsletter,
                'theme' => Theme::for($this->newsletter),
                'code' => $this->code,
                'minutes' => PaidReaders::CODE_MINUTES,
            ]);

        // Replies reach the owner, not the platform's sending address.
        if (filled($this->newsletter->paid_manual_contact)) {
            $message->replyTo($this->newsletter->paid_manual_contact, $this->newsletter->name);
        }

        return $message;
    }
}
