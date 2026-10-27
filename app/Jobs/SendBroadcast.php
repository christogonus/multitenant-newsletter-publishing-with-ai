<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queues one email per recipient, so a slow or failing address never holds up the rest.
 */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public Broadcast $broadcast) {}

    public function handle(): void
    {
        $count = 0;

        $this->broadcast->recipients()->chunkById(200, function (Collection $users) use (&$count) {
            $users->each(fn (User $user) => $user->notify(new BroadcastEmail($this->broadcast)));
            $count += $users->count();
        });

        $this->broadcast->update(['recipients_count' => $count, 'sent_at' => now()]);
    }
}
