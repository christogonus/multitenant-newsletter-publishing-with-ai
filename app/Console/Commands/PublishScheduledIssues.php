<?php

namespace App\Console\Commands;

use App\Actions\PublishIssue;
use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Models\Issue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs every minute from the scheduler and publishes issues whose time has come (OTO 7).
 */
#[Signature('newspub:publish-scheduled')]
#[Description('Publish scheduled issues that are due')]
class PublishScheduledIssues extends Command
{
    public function handle(PublishIssue $publishIssue): int
    {
        $published = 0;

        Issue::query()
            ->where('status', IssueStatus::Scheduled)
            ->where('scheduled_for', '<=', now())
            // Suspended owners' issues wait, still scheduled, until the suspension is lifted.
            ->whereHas('newsletter.user', fn ($users) => $users->whereNull('suspended_at'))
            ->with('newsletter.user')
            ->chunkById(100, function ($issues) use ($publishIssue, &$published) {
                foreach ($issues as $issue) {
                    if (! $issue->newsletter->user->owns(Product::Oto7Autopilot) || $issue->blockList() === []) {
                        $issue->update(['status' => IssueStatus::Draft, 'scheduled_for' => null]);

                        continue;
                    }

                    $publishIssue->handle($issue);
                    $published++;
                }
            });

        $this->info("Published {$published} scheduled issues.");

        return self::SUCCESS;
    }
}
