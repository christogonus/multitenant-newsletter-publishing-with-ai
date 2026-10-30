<?php

namespace App\Livewire\Account;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Notifications\PasswordChangeCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Replaces the shared starting password. Anyone who knows a buyer's email can sign in with it, so the
 * first change needs a code sent to the inbox: only the real owner can lock the account to a password of their own.
 */
class ChooseOwnPassword extends Component
{
    use PasswordValidationRules;

    public const CODE_MINUTES = 15;

    public const MAX_CODE_ATTEMPTS = 5;

    public bool $codeSent = false;

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function sendCode(): void
    {
        $user = Auth::user();
        $limiterKey = 'password-change-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $this->addError('code', 'Too many codes sent. Try again in '.ceil(RateLimiter::availableIn($limiterKey) / 60).' minutes.');

            return;
        }

        RateLimiter::hit($limiterKey, 600);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey(), ['hash' => $this->hashCode($code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));
        $user->notify(new PasswordChangeCode($code));

        $this->resetValidation();
        $this->codeSent = true;
        $this->dispatch('toast', message: 'Code sent to '.$user->email.'.', tone: 'success');
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['required', 'digits:6'],
            'password' => $this->passwordRules(),
        ], ['code.digits' => 'The code is the 6 digits from the email.']);

        if (! $this->checkCode($this->code)) {
            $this->addError('code', 'That code is wrong or has expired. Send a new one.');

            return;
        }

        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($this->password),
            'uses_default_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // Keep this session signed in; AuthenticateSession signs out every other one, including any opened with the starting password.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        session()->flash('status', 'password-updated');
        $this->redirectRoute('account');
    }

    public function render(): View
    {
        return view('livewire.account.choose-own-password');
    }

    /**
     * Each code works once and allows a few wrong guesses before it is thrown away.
     */
    private function checkCode(string $code): bool
    {
        $stored = Cache::get($this->cacheKey());

        if (! is_array($stored)) {
            return false;
        }

        if (hash_equals($stored['hash'], $this->hashCode(trim($code)))) {
            Cache::forget($this->cacheKey());

            return true;
        }

        $stored['attempts']++;
        $stored['attempts'] >= self::MAX_CODE_ATTEMPTS
            ? Cache::forget($this->cacheKey())
            : Cache::put($this->cacheKey(), $stored, now()->addMinutes(self::CODE_MINUTES));

        return false;
    }

    private function cacheKey(): string
    {
        return 'password-change-code:'.Auth::id();
    }

    /**
     * Throws away a code that is waiting to be typed, for when an admin moves the account to another email.
     */
    public static function forgetCode(User $user): void
    {
        Cache::forget('password-change-code:'.$user->id);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', Auth::id().'|'.$code, (string) config('app.key'));
    }
}
