<?php

namespace App\Notifications;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an existing customer what one purchase unlocked (a product, an order bump, or both) and where to find it.
 */
class UpgradeUnlocked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $transactionId) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Nothing to announce if everything on the transaction was refunded before the email went out.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->items($notifiable)->isNotEmpty();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $items = $this->items($notifiable);
        $first = $items->first();

        $message = (new MailMessage)
            ->subject($items->count() === 1 ? $first->itemLabel().' is unlocked' : 'Your upgrades are unlocked')
            ->greeting('Thanks, '.$notifiable->name.'!')
            ->line($items->count() === 1 ? 'Your purchase is ready to use:' : 'Everything you just bought is ready to use:');

        foreach ($items as $item) {
            $message->line('**'.$item->itemLabel().'** – '.($item->bumpOffer ? ($item->bumpOffer->description ?: 'Ready in your downloads.') : $item->product_key->benefit()));
        }

        $product = $items->firstWhere('product_key', '!=', null)?->product_key;

        return $message
            ->action($product ? 'Open '.$product->label() : 'Open my downloads', route($product ? $product->homeRoute() : 'downloads.index'))
            ->line('Sign in with '.$notifiable->email.'. If you have forgotten your password, use "Forgot password" on the sign-in page.');
    }

    /**
     * The standing items bought on this transaction, order bump last.
     *
     * @return Collection<int, Purchase>
     */
    private function items(User $notifiable): Collection
    {
        return $notifiable->purchases()
            ->standing()
            ->where('transaction_id', $this->transactionId)
            ->with('bumpOffer')
            ->orderByRaw('bump_offer_id is not null')
            ->oldest('id')
            ->get();
    }
}
