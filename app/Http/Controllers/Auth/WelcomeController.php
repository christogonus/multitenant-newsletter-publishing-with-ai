<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeBuyer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The set-password step a buyer reaches from the welcome email.
 */
class WelcomeController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, User $user, string $hash): View|RedirectResponse
    {
        if (! hash_equals(WelcomeBuyer::passwordFingerprint($user), $hash)) {
            return redirect()->route('login')->with('status', 'That link has already been used. Sign in, or use "Forgot password".');
        }

        return view('auth.welcome', ['user' => $user, 'action' => $request->fullUrl()]);
    }

    public function store(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless(hash_equals(WelcomeBuyer::passwordFingerprint($user), $hash), 403);

        $validated = $request->validate([
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'terms_accepted_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
