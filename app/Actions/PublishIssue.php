<?php

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Jobs\PushIssueJob;
use App\Models\Issue;
use App\Support\Entitlements;
use App\Support\PublicPageCache;
use Illuminate\Support\Str;

/**
 * Makes an issue live: gives it a public slug, busts the page cache and, with OTO 1 Pro auto-publish, pushes it to connected platforms.
 */
class PublishIssue
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Issue $issue): Issue
    {
        $issue->forceFill([
            'status' => IssueStatus::Published,
            'public_slug' => $issue->public_slug ?: $this->uniqueSlug($issue),
            'published_at' => $issue->published_at ?? now(),
            'scheduled_for' => null,
        ])->save();

        $newsletter = $issue->newsletter;
        PublicPageCache::bust($newsletter);

        if ($newsletter->auto_publish && $this->entitlements->canAutoPublish($newsletter->user)) {
            foreach ($newsletter->platformConnections()->where('status', 'connected')->get() as $connection) {
                PushIssueJob::dispatch($issue, $connection);
            }
        }

        return $issue;
    }

    public function unpublish(Issue $issue): Issue
    {
        $issue->update(['status' => IssueStatus::Draft]);
        PublicPageCache::bust($issue->newsletter);

        return $issue;
    }

    private function uniqueSlug(Issue $issue): string
    {
        $base = Str::limit(Str::slug($issue->title) ?: 'issue', 80, '');
        $slug = $base;
        $suffix = 2;

        while (Issue::query()->where('newsletter_id', $issue->newsletter_id)->where('public_slug', $slug)->whereKeyNot($issue->id)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
