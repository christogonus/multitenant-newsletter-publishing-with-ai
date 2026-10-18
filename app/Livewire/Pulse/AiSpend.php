<?php

namespace App\Livewire\Pulse;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Pulse card: AI spend by kind and by heaviest buyer for the selected period.
 * Values are recorded in hundredths of a cent by App\Ai\AiUsageRecorder.
 */
#[Lazy]
class AiSpend extends Card
{
    public function render(): View
    {
        $byKind = $this->aggregate('ai_spend', ['sum', 'count']);
        $byUser = $this->aggregate('ai_spend_user', ['sum', 'count'], 'sum', limit: 10);
        $users = User::query()->whereIn('id', $byUser->pluck('key'))->pluck('email', 'id');

        return view('livewire.pulse.ai-spend', [
            'total' => $byKind->sum('sum') / 10_000,
            'calls' => (int) $byKind->sum('count'),
            'threshold' => (float) config('newspub.ai.daily_spend_alert'),
            'byKind' => $byKind->map(fn ($row) => (object) ['kind' => $row->key, 'cost' => $row->sum / 10_000, 'calls' => (int) $row->count]),
            'byUser' => $byUser->map(fn ($row) => (object) ['email' => $users[(int) $row->key] ?? 'Deleted user', 'cost' => $row->sum / 10_000, 'calls' => (int) $row->count]),
        ]);
    }
}
