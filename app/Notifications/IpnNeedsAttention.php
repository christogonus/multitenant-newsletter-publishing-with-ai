<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * System email to admins when a sale was recorded but needs a hand to finish, such as a bump with nothing set up.
 */
class IpnNeedsAttention extends Notification
{
    use Queueable;

    public function __construct(public string $problem) {}

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
            ->subject('A sale needs your attention')
            ->line($this->problem)
            ->action('Open order bumps', route('admin.bumps'));
    }
}
