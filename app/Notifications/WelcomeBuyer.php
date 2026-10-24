<?php

namespace App\Notifications;

use App\Enums\Product;
use App\Models\User;
use App\Support\GettingStarted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * System email sent when the IPN creates an account: login details, what was bought and a first-time setup guide.
 * Accounts still on the default password are shown it; any other account gets a signed set-password link instead.
 */
class WelcomeBuyer extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $defaultPassword = (string) config('newspub.default_password');
        $showsDefaultPassword = $notifiable->uses_default_password && $defaultPassword !== '';

        return (new MailMessage)
            ->subject($showsDefaultPassword ? 'Your '.config('app.name').' login details and quick-start guide' : 'Your '.config('app.name').' account is ready')
            ->replyTo((string) config('newspub.support_email'))
            ->markdown('mail.welcome-buyer', [
                'name' => $notifiable->name,
                'email' => $notifiable->email,
                'loginUrl' => route('login'),
                'defaultPassword' => $showsDefaultPassword ? $defaultPassword : null,
                'setPasswordUrl' => $showsDefaultPassword ? null : self::setPasswordUrl($notifiable),
                'products' => collect(Product::cases())
                    ->filter(fn (Product $product) => $notifiable->owns($product))
                    ->map(fn (Product $product) => ['label' => $product->label(), 'benefit' => $product->benefit()])
                    ->values()
                    ->all(),
                'steps' => GettingStarted::steps(),
                'accessUrl' => route('access'),
                'supportEmail' => (string) config('newspub.support_email'),
            ]);
    }

    /**
     * A signed link that stops working once the password has been changed.
     */
    public static function setPasswordUrl(User $user): string
    {
        return URL::temporarySignedRoute('welcome.show', now()->addDays(7), [
            'user' => $user->getKey(),
            'hash' => self::passwordFingerprint($user),
        ]);
    }

    public static function passwordFingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key')), 0, 32);
    }
}
