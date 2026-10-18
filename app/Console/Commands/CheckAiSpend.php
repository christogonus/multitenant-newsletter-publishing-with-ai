<?php

namespace App\Console\Commands;

use App\Models\AiUsage;
use App\Models\User;
use App\Notifications\AiSpendAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts admins when AI spend in the last 24 hours passes the configured threshold.
 */
#[Signature('newspub:check-ai-spend')]
#[Description('Alert admins when daily AI spend passes the threshold')]
class CheckAiSpend extends Command
{
    public function handle(): int
    {
        $spend = (float) AiUsage::query()->where('created_at', '>=', now()->subDay())->sum('cost');
        $threshold = (float) config('newspub.ai.daily_spend_alert');

        $this->info(sprintf('AI spend in the last 24 hours: $%.2f (alert at $%.2f)', $spend, $threshold));

        if ($threshold > 0 && $spend >= $threshold) {
            Log::warning('AI spend alert', ['spend' => $spend, 'threshold' => $threshold]);
            Notification::send(User::query()->where('is_admin', true)->get(), new AiSpendAlert($spend, $threshold));
        }

        return self::SUCCESS;
    }
}
