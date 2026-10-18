<?php

namespace App\Livewire\Admin;

use App\Models\AiUsage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin: AI spend by period, by kind and by heaviest users, against the alert threshold.
 */
#[Title('Admin · AI spend')]
class Spend extends Component
{
    public function render(): View
    {
        $since = fn (int $days) => (float) AiUsage::query()->where('created_at', '>=', now()->subDays($days))->sum('cost');

        return view('livewire.admin.spend', [
            'today' => $since(1),
            'week' => $since(7),
            'month' => $since(30),
            'threshold' => (float) config('newspub.ai.daily_spend_alert'),
            'byKind' => AiUsage::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->select('kind', DB::raw('count(*) as calls'), DB::raw('sum(cost) as total'), DB::raw('sum(input_tokens + output_tokens) as tokens'))
                ->groupBy('kind')
                ->orderByDesc('total')
                ->get(),
            'topUsers' => AiUsage::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->whereNotNull('user_id')
                ->select('user_id', DB::raw('count(*) as calls'), DB::raw('sum(cost) as total'))
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->limit(10)
                ->with('user:id,email')
                ->get(),
        ]);
    }
}
