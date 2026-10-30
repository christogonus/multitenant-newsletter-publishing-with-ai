<?php

namespace App\Livewire\Account;

use App\Models\User;
use App\Notifications\EmailChangeCode;
use App\Notifications\EmailChanged;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Moves the sign-in email. Password resets follow the email, so changing it hands over the account: it takes the
 * current password and a code sent to the new inbox, and the old inbox is told once it has happened.
 */
class ChangeEmail extends Component
{
    public const CODE_MINUTES = 15;

    public const MAX_CODE_ATTEMPTS = 5;

    public const MAX_REQUESTS = 5;

    public string $email = '';

    public string $password = '';

    public string $code = '';

    public ?string $pendingEmail = null;

    public function mount(): void
    {
        $this->pendingEmail = Cache::get($this->cacheKey())['email'] ?? null;
    }

    public function sendCode(): void
    {
        $user = $this->account();
        $limiterKey = 'email-change-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_REQUESTS)) {
            $this->addError('email', 'Too many tries. Try again in '.ceil(RateLimiter::availableIn($limiterKey) / 60).' minutes.');

            return;
        }

        // Counted before the checks, so this can't be used to guess the password or test which emails are taken.
        RateLimiter::hit($limiterKey, 600);

        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => $this->emailRules($user),
            'password' => ['required', 'current_password:web'],
        ], $this->emailMessages());

        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey(), ['email' => $this->email, 'hash' => $this->hashCode($code)], now()->addMinutes(self::CODE_MINUTES));
        Cache::put($this->attemptsKey(), 0, now()->addMinutes(self::CODE_MINUTES));
        Notification::route('mail', $this->email)->notify(new EmailChangeCode($code));

        $this->pendingEmail = $this->email;
        $this->reset('email', 'password', 'code');
        $this->resetValidation();
        $this->dispatch('toast', message: 'Code sent to '.$this->pendingEmail.'.', tone: 'success');
    }

    public function confirm(): void
    {
        $user = $this->account();

        $this->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'The code is the 6 digits from the email.']);

        $newEmail = $this->checkCode($this->code);

        if ($newEmail === null) {
            $this->addError('code', 'That code is wrong or has expired. Start over to get a new one.');

            return;
        }

        // Someone else may have taken the address since the code was sent.
        $validator = Validator::make(['email' => $newEmail], ['email' => $this->emailRules($user)], $this->emailMessages());

        if ($validator->fails()) {
            $this->addError('code', $validator->errors()->first('email'));

            return;
        }

        $oldEmail = $user->email;
        $user->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();
        Notification::route('mail', $oldEmail)->notify(new EmailChanged($newEmail));

        session()->flash('status', 'email-changed');
        $this->redirectRoute('account');
    }

    public function startOver(): void
    {
        $this->account();

        Cache::forget($this->cacheKey());
        Cache::forget($this->attemptsKey());

        $this->reset('email', 'password', 'code', 'pendingEmail');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.account.change-email');
    }

    /**
     * The signed-in account. Shared demo accounts keep their email, and the starting password proves nothing about who is signed in.
     */
    private function account(): User
    {
        $user = Auth::user();

        abort_if($user->is_demo || $user->uses_default_password, 403);

        return $user;
    }

    /**
     * @return array<int, mixed>
     */
    private function emailRules(User $user): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            Rule::notIn([Str::lower($user->email)]),
            Rule::unique('users', 'email')->ignore($user->id),
            // Purchases are matched on the email they were paid with, so it must keep pointing at the account that paid.
            Rule::unique('purchases', 'buyer_email')->ignore($user->id, 'user_id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emailMessages(): array
    {
        return [
            'email.not_in' => 'That is already your email.',
            'email.unique' => 'That email is already used by another account.',
        ];
    }

    /**
     * The email the code unlocks, or null. Each code works once and allows a few wrong guesses before it is thrown
     * away; the guess is counted atomically first, so guesses sent in parallel can't share an attempt.
     */
    private function checkCode(string $code): ?string
    {
        $stored = Cache::get($this->cacheKey());

        if (! is_array($stored)) {
            return null;
        }

        $attempt = (int) Cache::increment($this->attemptsKey());

        if ($attempt <= self::MAX_CODE_ATTEMPTS && hash_equals($stored['hash'], $this->hashCode(trim($code)))) {
            Cache::forget($this->cacheKey());
            Cache::forget($this->attemptsKey());

            return $stored['email'];
        }

        if ($attempt >= self::MAX_CODE_ATTEMPTS) {
            Cache::forget($this->cacheKey());
            Cache::forget($this->attemptsKey());
        }

        return null;
    }

    private function cacheKey(): string
    {
        return 'email-change-code:'.Auth::id();
    }

    private function attemptsKey(): string
    {
        return $this->cacheKey().':attempts';
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', Auth::id().'|'.$code, (string) config('app.key'));
    }
}
