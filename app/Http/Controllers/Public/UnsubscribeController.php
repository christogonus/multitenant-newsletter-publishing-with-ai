<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Signed links that let a reader leave a publication or erase their details.
 * The pages carry the publication's own branding, never the app's.
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, Subscriber $subscriber): View
    {
        $newsletter = $subscriber->newsletter;
        $this->linkToPublication($newsletter);

        return view('public.unsubscribe', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'subscriber' => $subscriber,
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
        $newsletter = $subscriber->newsletter;
        $subscriber->referrals()->update(['referred_by_id' => null]);
        $subscriber->delete();

        $this->linkToPublication($newsletter);

        return view('public.deleted', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
        ]);
    }

    /**
     * These pages are served from the app host, so the publication layout's links point at the publication's subdomain.
     */
    private function linkToPublication(Newsletter $newsletter): void
    {
        URL::defaults(['publication' => $newsletter->subdomain]);
    }
}
