<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lets an account holder erase their account, publications, readers and files.
 */
class DeleteAccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

        DB::transaction(function () use ($user) {
            // Delete publications one by one so their model events remove uploaded files.
            $user->newsletters()->each(fn ($newsletter) => $newsletter->delete());
            $user->delete();
        });

        return redirect()->route('login')->with('status', 'Your account and everything in it has been deleted.');
    }
}
