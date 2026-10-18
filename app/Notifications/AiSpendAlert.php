<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * System email to admins when AI spend passes the daily threshold.
 */
class AiSpendAlert extends Notification
{
    use Queueable;

    public function __construct(public float $spend, public float $threshold) {}

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
            ->subject(sprintf('AI spend alert: $%.2f in 24 hours', $this->spend))
            ->line(sprintf('AI spend in the last 24 hours reached $%.2f, above the $%.2f alert level.', $this->spend, $this->threshold))
            ->action('Open the spend page', route('admin.spend'));
    }
}
