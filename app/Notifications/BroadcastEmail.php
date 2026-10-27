<?php

namespace App\Notifications;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * One customer's copy of an admin broadcast. It carries a one-click unsubscribe header, which Gmail and Yahoo
 * expect from bulk senders before they place mail in the inbox.
 */
class BroadcastEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public Broadcast $broadcast) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Someone who unsubscribed or was suspended after the send started is skipped.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->emails_unsubscribed_at === null && ! $notifiable->isSuspended();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $broadcast = $this->broadcast;
        $unsubscribeUrl = self::unsubscribeUrl($notifiable);

        return (new MailMessage)
            ->from($broadcast->from_email, $broadcast->from_name)
            ->replyTo($broadcast->from_email, $broadcast->from_name)
            ->subject(self::personalise($broadcast->subject, $notifiable))
            ->markdown('mail.broadcast', [
                'body' => self::personalise(self::tidy($broadcast->body), $notifiable),
                'buttonLabel' => $broadcast->button_label,
                'buttonUrl' => $broadcast->button_url,
                'unsubscribeUrl' => $unsubscribeUrl,
            ])
            ->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }

    /**
     * A permanent signed link that stops admin broadcasts to this account. Account emails (receipts, codes) still arrive.
     */
    public static function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('emails.unsubscribe', ['user' => $user]);
    }

    /**
     * Replaces {name} and {first_name} with the customer's name.
     */
    public static function personalise(string $text, User $user): string
    {
        return strtr($text, [
            '{name}' => $user->name,
            '{first_name}' => strtok($user->name, ' ') ?: $user->name,
        ]);
    }

    /**
     * Drops leading spaces so pasted text is never shown as a code block.
     */
    private static function tidy(string $body): string
    {
        return implode("\n", array_map('ltrim', preg_split('/\R/', trim($body))));
    }
}
