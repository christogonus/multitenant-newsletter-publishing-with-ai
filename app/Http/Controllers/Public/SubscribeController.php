<?php

namespace App\Http\Controllers\Public;

use App\Actions\SubscribeReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Support\PublicLinks;
use Illuminate\Http\RedirectResponse;

class SubscribeController extends Controller
{
    public function __invoke(SubscribeRequest $request, SubscribeReader $subscribeReader): RedirectResponse
    {
        $newsletter = $request->attributes->get('newsletter');

        if ($request->looksLikeABot()) {
            return redirect(PublicLinks::route('thanks'));
        }

        $subscriber = $subscribeReader->handle(
            newsletter: $newsletter,
            email: $request->validated('email'),
            ip: $request->ip(),
            referralCode: $request->input('ref') ?: $request->cookie('np_ref_'.$newsletter->id),
        );

        if ($subscriber->wasRecentlyCreated) {
            $request->session()->put('subscribed.'.$newsletter->id, $subscriber->id);
        } else {
            $request->session()->forget('subscribed.'.$newsletter->id);
        }

        return redirect(PublicLinks::route('thanks'));
    }
}
