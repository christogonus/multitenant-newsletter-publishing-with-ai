<?php

namespace App\Jobs;

use App\Enums\ConnectionStatus;
use App\Integrations\PlatformException;
use App\Integrations\PlatformManager;
use App\Models\Issue;
use App\Models\IssueDelivery;
use App\Models\PlatformConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one issue to one connected platform as a draft or campaign, with retries and a plain-English error.
 */
class PushIssueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public function __construct(public Issue $issue, public PlatformConnection $platformConnection)
    {
        $this->onQueue('publishing');
    }

    public function handle(PlatformManager $platforms): void
    {
        $delivery = IssueDelivery::firstOrCreate(
            ['issue_id' => $this->issue->id, 'platform_connection_id' => $this->platformConnection->id],
            ['status' => 'pending'],
        );

        if ($delivery->status === 'sent' && $delivery->remote_id) {
            return;
        }

        try {
            $remoteId = $platforms->for($this->platformConnection->platform)->pushIssue($this->platformConnection, $this->issue);
        } catch (PlatformException $exception) {
            $delivery->update(['status' => 'failed', 'error' => $exception->getMessage().' '.$exception->fix]);
            $this->platformConnection->update([
                'last_error' => $exception->getMessage().' '.$exception->fix,
                'status' => $exception->credentialsInvalid ? ConnectionStatus::Error : $this->platformConnection->status,
            ]);

            if ($exception->retryable) {
                throw $exception;
            }

            $this->fail($exception);

            return;
        }

        $delivery->update(['status' => 'sent', 'remote_id' => $remoteId, 'error' => null]);
        $this->platformConnection->update(['last_pushed_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        IssueDelivery::query()
            ->where('issue_id', $this->issue->id)
            ->where('platform_connection_id', $this->platformConnection->id)
            ->where('status', '!=', 'sent')
            ->update(['status' => 'failed']);
    }
}
