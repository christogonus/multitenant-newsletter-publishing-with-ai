<?php

namespace App\Support;

use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Models\NetworkListing;
use App\Models\Newsletter;
use Illuminate\Support\Collection;

/**
 * Recommendation swaps shown on public pages (OTO 10). Partners who published recently get placement priority.
 */
class NetworkRecommendations
{
    public const LIMIT = 3;

    /**
     * @return Collection<int, NetworkListing>
     */
    public static function for(Newsletter $newsletter): Collection
    {
        if (! $newsletter->user?->owns(Product::Oto10Network)) {
            return collect();
        }

        $listing = $newsletter->networkListing;

        if (! $listing) {
            return collect();
        }

        return $listing->partners()
            ->filter(fn (NetworkListing $partner) => $partner->is_listed && $partner->newsletter && ! $partner->newsletter->isModerated() && ! $partner->newsletter->isOnHold())
            ->sortByDesc(fn (NetworkListing $partner) => self::activityScore($partner))
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Seeded listings are always shown; member listings rank by how recently they published.
     */
    public static function activityScore(NetworkListing $listing): int
    {
        $lastPublished = $listing->newsletter->issues()
            ->where('status', IssueStatus::Published)
            ->max('published_at');

        if (! $lastPublished) {
            return $listing->is_seeded ? 1 : 0;
        }

        return max(1, 100 - (int) now()->diffInDays($lastPublished, true));
    }
}
