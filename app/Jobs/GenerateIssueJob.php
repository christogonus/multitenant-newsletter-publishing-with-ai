<?php

namespace App\Jobs;

use App\Ai\Contracts\NewsletterWriter;
use App\Enums\GenerationStage;
use App\Enums\IssueStatus;
use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Researches, writes and lays out one issue. The editor polls the issue row to show the three stages.
 */
class GenerateIssueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [15];

    public function __construct(public Issue $issue, public bool $withResearch = true) {}

    public function handle(NewsletterWriter $writer): void
    {
        $issue = $this->issue;
        $newsletter = $issue->newsletter;

        $issue->update(['generation_stage' => GenerationStage::Researching, 'generation_error' => null]);
        $research = $this->withResearch ? $writer->research($newsletter) : null;

        $issue->update(['generation_stage' => GenerationStage::Writing]);
        $draft = $writer->writeIssue($newsletter, $issue->topic, $research);

        $issue->update(['generation_stage' => GenerationStage::Designing]);

        $issue->update([
            'title' => $draft->title,
            'subject_line' => $draft->subjectLine,
            'preheader' => $draft->preheader,
            'blocks' => $draft->blocks,
            'status' => $issue->scheduled_for ? IssueStatus::Scheduled : IssueStatus::Draft,
            'generation_stage' => GenerationStage::Done,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->issue->update([
            'status' => IssueStatus::Failed,
            'generation_error' => 'The writer could not finish this issue. Press "Try again" and it will start over.',
        ]);

        if ($exception) {
            report($exception);
        }
    }
}
