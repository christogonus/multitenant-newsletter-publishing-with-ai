<?php

namespace App\Support;

use App\Actions\SubscribeReader;
use App\Enums\ConnectionStatus;
use App\Enums\PaidPlan;
use App\Enums\PaymentProvider;
use App\Integrations\Payments\PaymentException;
use App\Integrations\Payments\PaymentGateways;
use App\Models\Newsletter;
use App\Models\PaymentAccount;
use App\Models\Subscriber;
use App\Notifications\ReaderSignInCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Paying readers of a publication (OTO 4): email-code sign-in, access checks and granting access after payment.
 */
class PaidReaders
{
    public const CODE_MINUTES = 15;

    public const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private PaymentGateways $gateways, private SubscribeReader $subscribeReader) {}

    /**
     * The reader email this browser has confirmed for the publication, if any.
     */
    public function signedInEmail(Request $request, Newsletter $newsletter): ?string
    {
        return $request->session()->get('paid_reader.'.$newsletter->id);
    }

    public function signIn(Request $request, Newsletter $newsletter, string $email): void
    {
        $request->session()->regenerate();
        $request->session()->put('paid_reader.'.$newsletter->id, Str::lower($email));
    }

    public function signOut(Request $request, Newsletter $newsletter): void
    {
        $request->session()->forget(['paid_reader.'.$newsletter->id, 'paid_reader_pending.'.$newsletter->id]);
    }

    /**
     * Emails a six-digit code. Returns false when this email has asked for too many codes recently.
     */
    public function sendCode(Newsletter $newsletter, string $email): bool
    {
        $email = Str::lower(trim($email));
        $limiterKey = 'paid-reader-code-sent:'.$newsletter->id.':'.sha1($email);

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return false;
        }

        RateLimiter::hit($limiterKey, 600);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->codeKey($newsletter, $email), ['hash' => $this->hashCode($code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));

        Notification::route('mail', $email)->notify(new ReaderSignInCode($newsletter, $code));

        return true;
    }

    /**
     * Checks a code. Each code works once and allows a few wrong guesses before it is thrown away.
     */
    public function checkCode(Newsletter $newsletter, string $email, string $code): bool
    {
        $key = $this->codeKey($newsletter, Str::lower($email));
        $stored = Cache::get($key);

        if (! is_array($stored)) {
            return false;
        }

        if (hash_equals($stored['hash'], $this->hashCode(trim($code)))) {
            Cache::forget($key);

            return true;
        }

        $stored['attempts']++;
        $stored['attempts'] >= self::MAX_CODE_ATTEMPTS
            ? Cache::forget($key)
            : Cache::put($key, $stored, now()->addMinutes(self::CODE_MINUTES));

        return false;
    }

    public function subscriber(Newsletter $newsletter, ?string $email): ?Subscriber
    {
        return $email ? Subscriber::query()->whereBelongsTo($newsletter)->where('email', Str::lower($email))->first() : null;
    }

    /**
     * Whether this email may read locked issues. Lapsed Stripe and PayPal subscriptions are checked once more,
     * so renewals keep working without webhooks.
     */
    public function hasAccess(Newsletter $newsletter, ?string $email): bool
    {
        $subscriber = $this->subscriber($newsletter, $email);

        if (! $subscriber) {
            return false;
        }

        return $subscriber->hasPaidAccess() || $this->renew($subscriber);
    }

    /**
     * Asks the payment provider whether a lapsed subscription has renewed. At most once an hour per reader.
     */
    public function renew(Subscriber $subscriber): bool
    {
        if (! in_array($subscriber->paid_via, PaymentProvider::connectable(), true) || ! $subscriber->payment_reference) {
            return false;
        }

        if (! Cache::add('paid-reader-renewal-check:'.$subscriber->id, true, now()->addHour())) {
            return false;
        }

        $account = $this->account($subscriber->newsletter, $subscriber->paid_via);

        if (! $account) {
            return false;
        }

        try {
            $paidUntil = $this->gateways->for($subscriber->paid_via)->paidUntil($account, $subscriber->payment_reference);
        } catch (PaymentException $exception) {
            report($exception);

            return false;
        }

        if (! $paidUntil?->isFuture()) {
            return false;
        }

        $subscriber->update(['paid_until' => $paidUntil]);

        return true;
    }

    /**
     * Gives a reader paid access, adding them to the list first if they are new.
     */
    public function grant(
        Newsletter $newsletter,
        string $email,
        CarbonInterface $paidUntil,
        PaymentProvider $paidVia,
        ?PaidPlan $plan = null,
        ?string $paymentReference = null,
        ?string $ip = null,
    ): Subscriber {
        $subscriber = $this->subscribeReader->handle($newsletter, $email, $ip, source: 'paid');

        $subscriber->update([
            'paid_until' => $paidUntil,
            'paid_via' => $paidVia,
            'paid_plan' => $plan,
            'payment_reference' => $paymentReference,
        ]);

        return $subscriber;
    }

    /**
     * Ties a checkout to one reader of one publication, so a payment cannot be claimed by someone else.
     */
    public function reference(Newsletter $newsletter, string $email): string
    {
        return substr(hash_hmac('sha256', $newsletter->id.'|'.Str::lower($email), (string) config('app.key')), 0, 40);
    }

    /**
     * The owner's connected payment accounts, Stripe first.
     *
     * @return Collection<int, PaymentAccount>
     */
    public function accounts(Newsletter $newsletter): Collection
    {
        return $newsletter->paymentAccounts()
            ->where('status', ConnectionStatus::Connected)
            ->get()
            ->sortBy(fn (PaymentAccount $account) => array_search($account->provider, PaymentProvider::connectable(), true))
            ->values();
    }

    public function account(Newsletter $newsletter, PaymentProvider $provider): ?PaymentAccount
    {
        return $newsletter->paymentAccounts()->where('provider', $provider)->where('status', ConnectionStatus::Connected)->first();
    }

    /**
     * The plans the owner offers, with their reader-facing prices.
     *
     * @return list<array{plan: PaidPlan, price: string}>
     */
    public function plans(Newsletter $newsletter): array
    {
        return collect(PaidPlan::cases())
            ->filter(fn (PaidPlan $plan) => $newsletter->paidPriceCents($plan) !== null)
            ->map(fn (PaidPlan $plan) => ['plan' => $plan, 'price' => $newsletter->formatPaidPrice($newsletter->paidPriceCents($plan))])
            ->values()
            ->all();
    }

    private function codeKey(Newsletter $newsletter, string $email): string
    {
        return 'paid-reader-code:'.$newsletter->id.':'.sha1($email);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
