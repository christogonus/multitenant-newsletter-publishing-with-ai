<?php

namespace App\Actions;

use App\Enums\GenerationStage;
use App\Enums\IssueStatus;
use App\Exceptions\QuotaExceededException;
use App\Jobs\GenerateIssueJob;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Support\Entitlements;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks the weekly quota, creates the issue row as "generating" and queues the writer.
 */
class StartIssueGeneration
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @throws QuotaExceededException
     */
    public function handle(Newsletter $newsletter, ?string $topic = null, ?Issue $retry = null, ?CarbonInterface $scheduledFor = null): Issue
    {
        $user = $newsletter->user;

        $issue = Cache::lock('generate-issue:'.$user->id, 10)->block(5, function () use ($newsletter, $user, $topic, $retry, $scheduledFor) {
            if (! $retry && ! $this->entitlements->canGenerateIssues($user)) {
                throw QuotaExceededException::forUser($user, $this->entitlements);
            }

            return DB::transaction(function () use ($newsletter, $topic, $retry, $scheduledFor) {
                if ($retry) {
                    $retry->update([
                        'status' => IssueStatus::Generating,
                        'generation_stage' => GenerationStage::Researching,
                        'generation_error' => null,
                    ]);

                    return $retry;
                }

                return $newsletter->issues()->create([
                    'title' => $topic ?: 'New issue',
                    'topic' => $topic,
                    'status' => IssueStatus::Generating,
                    'is_ai_generated' => true,
                    'generation_stage' => GenerationStage::Researching,
                    'scheduled_for' => $scheduledFor,
                ]);
            });
        });

        GenerateIssueJob::dispatch($issue)->onQueue($this->entitlements->generationQueue($user));

        return $issue;
    }
}
