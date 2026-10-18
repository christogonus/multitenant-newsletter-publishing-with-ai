<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\PublicLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reader-gets-reader links. Remembers the referrer for 30 days and lands on the publication home.
 */
class ReferralController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $newsletter = $request->attributes->get('newsletter');
        $code = substr(preg_replace('/[^a-z0-9]/', '', strtolower($code)), 0, 40);

        return redirect(PublicLinks::route('home', ['ref' => $code]))
            ->withCookie(cookie('np_ref_'.$newsletter->id, $code, 60 * 24 * 30));
    }
}
