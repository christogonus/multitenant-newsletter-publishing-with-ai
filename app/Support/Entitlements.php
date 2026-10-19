<?php

namespace App\Support;

use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * Answers "what may this buyer do?" from the Pennant features, which resolve from the purchases ledger.
 */
class Entitlements
{
    public function has(User $user, Product $product): bool
    {
        return Feature::for($user)->active($product->value);
    }

    /**
     * Drop every stored feature value for the user so the next check re-reads the ledger.
     */
    public function refresh(User $user): void
    {
        Feature::for($user)->forget(array_map(fn (Product $product) => $product->value, Product::cases()));
        Feature::flushCache();
    }

    /**
     * Issues per newsletter per week, or for OTO 2 the hidden fair-use ceiling across all newsletters.
     */
    public function weeklyIssueLimit(User $user): int
    {
        return $this->hasUnlimitedIssues($user)
            ? (int) config('newspub.limits.fair_use_weekly_issues')
            : (int) config('newspub.limits.weekly_issues');
    }

    /**
     * Whether the user sees "unlimited" instead of a number. The fair-use ceiling stays hidden.
     */
    public function hasUnlimitedIssues(User $user): bool
    {
        return $this->has($user, Product::Oto2Silver);
    }

    /**
     * AI-written issues started in the last seven days, excluding failed attempts. The weekly limit is per
     * newsletter, so this counts the given one; OTO 2's fair-use ceiling counts every newsletter the user owns.
     */
    public function issuesUsedThisWeek(User $user, Newsletter $newsletter): int
    {
        return Issue::query()
            ->when(
                $this->hasUnlimitedIssues($user),
                fn ($issues) => $issues->whereHas('newsletter', fn ($newsletters) => $newsletters->where('user_id', $user->id)),
                fn ($issues) => $issues->where('newsletter_id', $newsletter->id),
            )
            ->where('is_ai_generated', true)
            ->where('status', '!=', IssueStatus::Failed)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    public function remainingIssuesThisWeek(User $user, Newsletter $newsletter): int
    {
        return max(0, $this->weeklyIssueLimit($user) - $this->issuesUsedThisWeek($user, $newsletter));
    }

    public function canGenerateIssues(User $user, Newsletter $newsletter, int $count = 1): bool
    {
        return $this->has($user, Product::FrontEnd)
            && $this->remainingIssuesThisWeek($user, $newsletter) >= $count;
    }

    public function generationQueue(User $user): string
    {
        return $this->has($user, Product::Oto2Gold) ? 'generation-priority' : 'generation';
    }

    /**
     * The writing model for this user's issues: Gold buyers get the premium model when one is configured.
     */
    public function writingModel(User $user): ?string
    {
        $premium = config('newspub.ai.premium_model') ?: null;

        return $premium !== null && $this->has($user, Product::Oto2Gold)
            ? $premium
            : (config('newspub.ai.model') ?: null);
    }

    /**
     * Maximum publications, or null for unlimited. OTO 9 manages publications but does not add capacity.
     */
    public function publicationLimit(User $user): ?int
    {
        $limits = config('newspub.limits.publications');

        return match (true) {
            $this->has($user, Product::Oto2Silver) => null,
            $this->has($user, Product::Oto11Agency) => null,
            $this->has($user, Product::Oto5Dfy) => (int) $limits['oto5_dfy'],
            default => (int) $limits['base'],
        };
    }

    public function canCreatePublication(User $user): bool
    {
        if (! $this->has($user, Product::FrontEnd)) {
            return false;
        }

        $limit = $this->publicationLimit($user);

        return $limit === null || $user->newsletters()->count() < $limit;
    }

    /**
     * Maximum platform connections per publication: 0 without OTO 1, null (unlimited) with it.
     */
    public function connectionLimit(User $user): ?int
    {
        return $this->has($user, Product::Oto1Delivery) ? null : 0;
    }

    public function canAutoPublish(User $user): bool
    {
        return $this->has($user, Product::Oto1Delivery);
    }

    public function resellerCodeQuota(User $user): int
    {
        if (! $this->has($user, Product::Oto12Reseller)) {
            return 0;
        }

        $licenses = $user->purchases()->standing()->where('product_key', Product::Oto12Reseller)->count();

        return $licenses * (int) config('newspub.limits.reseller_codes_per_license');
    }
}
