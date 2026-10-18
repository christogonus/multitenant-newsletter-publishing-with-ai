<?php

namespace App\Http\Controllers\Public;

use App\Actions\SubscribeReader;
use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Models\LeadPage;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Support\PublicLinks;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * OTO 3 opt-in pages: a focused sign-up page that hands over a free gift.
 */
class LeadPageController extends Controller
{
    public function show(Request $request, string $leadPageSlug): Response
    {
        $newsletter = $request->attributes->get('newsletter');
        $leadPage = $this->leadPage($newsletter, $leadPageSlug);
        $leadPage->increment('views');

        return response()->view('public.lead', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'leadPage' => $leadPage,
        ]);
    }

    public function store(SubscribeRequest $request, SubscribeReader $subscribeReader, string $leadPageSlug): RedirectResponse
    {
        $newsletter = $request->attributes->get('newsletter');
        $leadPage = $this->leadPage($newsletter, $leadPageSlug);

        if ($request->looksLikeABot()) {
            return redirect(PublicLinks::route('thanks'));
        }

        $subscriber = $subscribeReader->handle(
            newsletter: $newsletter,
            email: $request->validated('email'),
            ip: $request->ip(),
            referralCode: $request->input('ref') ?: $request->cookie('np_ref_'.$newsletter->id),
            source: 'lead_page',
            leadPageId: $leadPage->id,
            name: $request->validated('name'),
        );

        $request->session()->put('subscribed.'.$newsletter->id, $subscriber->id);

        return redirect(PublicLinks::signed('lead.gift', ['leadPageSlug' => $leadPage->slug, 'subscriber' => $subscriber->id], days: 30));
    }

    public function gift(Request $request, string $leadPageSlug, Subscriber $subscriber): Response
    {
        $newsletter = $request->attributes->get('newsletter');
        $leadPage = $this->leadPage($newsletter, $leadPageSlug);
        abort_unless($subscriber->newsletter_id === $newsletter->id, 404);

        return response()->view('public.gift', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'leadPage' => $leadPage,
            'shareUrl' => PublicLinks::referralShareUrl($newsletter, $subscriber),
        ]);
    }

    private function leadPage(Newsletter $newsletter, string $slug): LeadPage
    {
        abort_unless($newsletter->user->owns(Product::Oto3Growth), 404);

        return $newsletter->leadPages()->where('slug', $slug)->where('is_live', true)->firstOrFail();
    }
}
