<?php

namespace App\Jobs;

use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use App\Integrations\PlatformException;
use App\Integrations\PlatformManager;
use App\Models\Newsletter;
use App\Models\PlatformConnection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes readers collected on the hosted page to every connected platform. The platform runs double opt-in.
 */
class SyncSubscribersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public int $uniqueFor = 120;

    public function __construct(public Newsletter $newsletter)
    {
        $this->onQueue('integrations');
    }

    public function uniqueId(): string
    {
        return (string) $this->newsletter->id;
    }

    public function handle(PlatformManager $platforms): void
    {
        if ($this->newsletter->isOnHold()) {
            return;
        }

        $connections = $this->newsletter->platformConnections()
            ->where('status', ConnectionStatus::Connected)
            ->where('platform', '!=', Platform::HtmlExport)
            ->get();

        foreach ($connections as $connection) {
            $this->syncConnection($platforms, $connection);
        }
    }

    private function syncConnection(PlatformManager $platforms, PlatformConnection $connection): void
    {
        $startedAt = now();

        $subscribers = $this->newsletter->subscribers()
            ->active()
            ->when($connection->last_synced_at, fn ($query) => $query->where('updated_at', '>=', $connection->last_synced_at))
            ->orderBy('id')
            ->limit(500)
            ->get();

        if ($subscribers->isEmpty()) {
            return;
        }

        try {
            $platforms->for($connection->platform)->syncSubscribers($connection, $subscribers);
        } catch (PlatformException $exception) {
            $connection->update([
                'last_error' => $exception->getMessage().' '.$exception->fix,
                'status' => $exception->credentialsInvalid ? ConnectionStatus::Error : $connection->status,
            ]);

            if ($exception->retryable) {
                throw $exception;
            }

            return;
        }

        $this->newsletter->subscribers()->whereIn('id', $subscribers->pluck('id'))->update(['synced_at' => $startedAt]);
        $connection->update(['last_synced_at' => $subscribers->count() === 500 ? $subscribers->last()->updated_at : $startedAt, 'last_error' => null]);

        if ($subscribers->count() === 500) {
            self::dispatch($this->newsletter)->delay(now()->addSeconds(5));
        }
    }
}
