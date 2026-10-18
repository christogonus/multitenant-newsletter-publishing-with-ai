<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * System email sent once when the IPN creates an account. Contains a signed set-password link.
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
        return (new MailMessage)
            ->subject('Your NewsPub AI account is ready')
            ->greeting('Welcome, '.$notifiable->name.'!')
            ->line('Thanks for your purchase. Your account is ready.')
            ->line('Click the button to choose your password. You will be writing your first newsletter a few minutes later.')
            ->action('Choose my password', self::setPasswordUrl($notifiable))
            ->line('This link works for 7 days. If it stops working, use "Forgot password" on the sign-in page.');
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
