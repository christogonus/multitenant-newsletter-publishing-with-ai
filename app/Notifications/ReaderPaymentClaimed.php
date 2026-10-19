<?php

namespace App\Notifications;

use App\Models\PaymentClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner a reader says they paid by a manual method, so they can check and activate them.
 */
class ReaderPaymentClaimed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PaymentClaim $claim) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newsletter = $this->claim->newsletter;
        $price = $newsletter->paidPriceCents($this->claim->plan);

        return (new MailMessage)
            ->subject($this->claim->email.' says they paid for '.$newsletter->name)
            ->greeting('A reader says they paid')
            ->line($this->claim->email.' says they paid for a '.mb_strtolower($this->claim->plan->label()).' subscription to '.$newsletter->name
                .($price ? ' ('.$newsletter->formatPaidPrice($price).')' : '').'.')
            ->line('Payment reference: '.($this->claim->reference ?: 'none given'))
            ->line('Check that the money arrived, then press Activate.')
            ->action('Review payments', route('sponsors.index', ['tab' => 'paid']));
    }
}
