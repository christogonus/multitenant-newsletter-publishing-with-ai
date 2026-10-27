<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Stops admin broadcasts for one account from the signed link in every broadcast. Opening the link asks first,
 * because mail scanners open links on their own; the POST (from the page or a one-click mail client) unsubscribes.
 */
class EmailUnsubscribeController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('emails.unsubscribe', ['user' => $user, 'done' => $user->emails_unsubscribed_at !== null, 'action' => $request->fullUrl()]);
    }

    public function store(Request $request, User $user): View|Response
    {
        $user->emails_unsubscribed_at ??= now();
        $user->save();

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response()->noContent(200);
        }

        return view('emails.unsubscribe', ['user' => $user, 'done' => true, 'action' => $request->fullUrl()]);
    }
}
