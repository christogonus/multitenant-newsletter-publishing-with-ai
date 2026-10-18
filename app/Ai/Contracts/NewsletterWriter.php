<?php

namespace App\Ai\Contracts;

use App\Ai\Data\IssueDraft;
use App\Enums\SocialChannel;
use App\Models\Issue;
use App\Models\Newsletter;

/**
 * Every AI call in the app goes through this interface, so an SDK upgrade touches one implementation.
 * Implementations record an ai_usage row for every generation.
 */
interface NewsletterWriter
{
    /**
     * Fresh, cached notes on what is happening in the publication's niche, or null when research is unavailable.
     */
    public function research(Newsletter $newsletter): ?string;

    public function writeIssue(Newsletter $newsletter, ?string $topic = null, ?string $research = null): IssueDraft;

    /**
     * @return list<string>
     */
    public function suggestTopics(Newsletter $newsletter, int $count = 10): array;

    public function repurpose(Issue $issue, SocialChannel $channel): string;

    /**
     * @param  array{subscribers: int, issues: int}  $stats
     * @return array{headline: string, about: string, audience: string, why_sponsor: list<string>, packages: list<array{name: string, description: string, price: string}>}
     */
    public function mediaKit(Newsletter $newsletter, array $stats): array;

    /**
     * @return array{headline: string, subheadline: string, bullets: list<string>, button_label: string, magnet_title: string, magnet_sections: list<array{heading: string, body: string}>}
     */
    public function leadPage(Newsletter $newsletter, ?string $idea = null): array;
}
