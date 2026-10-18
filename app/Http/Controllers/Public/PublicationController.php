<?php

namespace App\Http\Controllers\Public;

use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Support\NetworkRecommendations;
use App\Support\PublicLinks;
use App\Support\PublicPageCache;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Server-rendered, cached public pages. Reads published issues only.
 */
class PublicationController extends Controller
{
    public function home(Request $request): Response
    {
        $newsletter = $this->newsletter($request);

        return $this->cached($request, $newsletter, 'home', fn () => view('public.home', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'issues' => Issue::query()->publiclyVisible()->whereBelongsTo($newsletter)->latest('published_at')->limit(10)->get(),
            'recommendations' => NetworkRecommendations::for($newsletter),
        ])->render());
    }

    public function archive(Request $request): Response
    {
        $newsletter = $this->newsletter($request);
        $page = max(1, (int) $request->query('page', 1));

        return $this->cached($request, $newsletter, 'archive:'.$page, fn () => view('public.archive', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'issues' => Issue::query()->publiclyVisible()->whereBelongsTo($newsletter)->latest('published_at')->simplePaginate(25),
        ])->render());
    }

    public function issue(Request $request, string $slug): Response
    {
        $newsletter = $this->newsletter($request);

        $issue = Issue::query()
            ->publiclyVisible()
            ->whereBelongsTo($newsletter)
            ->where('public_slug', $slug)
            ->firstOrFail();

        $issue->setRelation('newsletter', $newsletter);

        return $this->cached($request, $newsletter, 'issue:'.$slug, fn () => view('public.issue', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'issue' => $issue,
            'recommendations' => NetworkRecommendations::for($newsletter),
        ])->render());
    }

    public function thanks(Request $request): Response
    {
        $newsletter = $this->newsletter($request);
        $subscriberId = $request->session()->get('subscribed.'.$newsletter->id);
        $subscriber = $subscriberId ? Subscriber::query()->whereBelongsTo($newsletter)->find($subscriberId) : null;
        $referralsEnabled = $newsletter->user->owns(Product::Oto3Growth);

        return response()->view('public.thanks', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'subscriber' => $subscriber,
            'referralsEnabled' => $referralsEnabled,
            'shareUrl' => $subscriber ? PublicLinks::referralShareUrl($newsletter, $subscriber) : null,
            'statusUrl' => $subscriber ? PublicLinks::signed('referrals', ['subscriber' => $subscriber->id]) : null,
        ]);
    }

    public function referrals(Request $request, Subscriber $subscriber): Response
    {
        $newsletter = $this->newsletter($request);
        abort_unless($subscriber->newsletter_id === $newsletter->id, 404);

        return response()->view('public.referrals', [
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'count' => $subscriber->referrals()->active()->count(),
            'shareUrl' => PublicLinks::referralShareUrl($newsletter, $subscriber),
            'unsubscribeUrl' => URL::signedRoute('readers.unsubscribe', ['subscriber' => $subscriber->id]),
        ]);
    }

    private function newsletter(Request $request): Newsletter
    {
        return $request->attributes->get('newsletter');
    }

    /**
     * @param  \Closure(): string  $render
     */
    private function cached(Request $request, Newsletter $newsletter, string $page, \Closure $render): Response
    {
        $hasFlash = $request->session()->has('errors') || $request->hasAny(['ref']);

        $html = $hasFlash
            ? $render()
            : PublicPageCache::remember($newsletter, PublicLinks::mode().':'.$page, $render);

        // The HTML is cached server-side; responses carry a session cookie, so shared caches must not store them.
        return response($html)->header('Cache-Control', 'private, max-age=60');
    }
}
