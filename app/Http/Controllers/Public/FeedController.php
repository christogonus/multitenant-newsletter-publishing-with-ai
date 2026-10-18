<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Support\PublicLinks;
use App\Support\PublicPageCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * RSS feed and sitemap for a publication, so readers can follow it and search engines can find every issue.
 */
class FeedController extends Controller
{
    public function rss(Request $request): Response
    {
        $newsletter = $this->newsletter($request);

        $xml = PublicPageCache::remember($newsletter, PublicLinks::mode().':feed', fn () => view('public.feed', [
            'newsletter' => $newsletter,
            'issues' => $this->issues($newsletter, 50),
        ])->render());

        return response($xml)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function sitemap(Request $request): Response
    {
        $newsletter = $this->newsletter($request);

        $xml = PublicPageCache::remember($newsletter, PublicLinks::mode().':sitemap', fn () => view('public.sitemap', [
            'newsletter' => $newsletter,
            'issues' => $this->issues($newsletter, 5000),
        ])->render());

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function newsletter(Request $request): Newsletter
    {
        return $request->attributes->get('newsletter');
    }

    /**
     * @return Collection<int, Issue>
     */
    private function issues(Newsletter $newsletter, int $limit): Collection
    {
        return Issue::query()
            ->publiclyVisible()
            ->whereBelongsTo($newsletter)
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->each(fn (Issue $issue) => $issue->setRelation('newsletter', $newsletter));
    }
}
