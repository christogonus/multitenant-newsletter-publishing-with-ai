<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Signed links that let a reader leave a publication or erase their details.
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, Subscriber $subscriber): View
    {
        return view('public.unsubscribe', [
            'subscriber' => $subscriber->load('newsletter'),
            'unsubscribeAction' => $request->fullUrl(),
            'deleteAction' => URL::signedRoute('readers.delete', ['subscriber' => $subscriber->id]),
            'done' => session('done'),
        ]);
    }

    public function store(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->update(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()]);

        return back()->with('done', 'You have been unsubscribed from '.$subscriber->newsletter->name.'.');
    }

    public function destroy(Subscriber $subscriber): View
    {
        $name = $subscriber->newsletter->name;
        $subscriber->referrals()->update(['referred_by_id' => null]);
        $subscriber->delete();

        return view('public.deleted', ['name' => $name]);
    }
}
