<?php

namespace App\Http\Controllers\Auth;

use App\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets an account holder erase their account, publications, readers and files.
 */
class DeleteAccountController extends Controller
{
    public function __invoke(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $request->validate(
            ['password' => ['required', 'current_password'], 'confirm' => ['required', 'in:DELETE']],
            ['password.current_password' => 'That password is not right.', 'confirm.in' => 'Type DELETE in capital letters to confirm.'],
        );

        $user = $request->user();

        // Sign out first: logging out saves a fresh remember token, which would re-insert a deleted user.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $deleteAccount->handle($user);

        return redirect()->route('login')->with('status', 'Your account and everything in it has been deleted.');
    }
}
