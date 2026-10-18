<?php

namespace App\Livewire\Issues;

use App\Actions\StartIssueGeneration;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Concerns\InteractsWithPublication;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Write a new issue')]
class Create extends Component
{
    use InteractsWithPublication;

    public string $topic = '';

    public function useTopic(string $topic): void
    {
        $this->topic = $topic;
    }

    public function generate(StartIssueGeneration $startIssueGeneration): void
    {
        $this->validate(['topic' => ['nullable', 'string', 'max:160']]);

        try {
            $issue = $startIssueGeneration->handle($this->newsletter, trim($this->topic) ?: null);
        } catch (QuotaExceededException $exception) {
            $this->addError('topic', $exception->getMessage());

            return;
        }

        $this->redirectRoute('issues.edit', $issue);
    }

    public function render(): View
    {
        $user = $this->user();
        $newsletter = $this->newsletter;

        $ideas = collect($newsletter->topic_pipeline ?? [])
            ->diff($newsletter->issues()->pluck('topic')->filter())
            ->take(6)
            ->values();

        if ($ideas->isEmpty()) {
            $ideas = collect([
                'The biggest '.$newsletter->niche.' story this week',
                '5 beginner mistakes in '.$newsletter->niche.' and how to avoid them',
                'A simple '.$newsletter->niche.' checklist for this month',
                'Myths about '.$newsletter->niche.' that people still believe',
            ]);
        }

        return view('livewire.issues.create', [
            'remaining' => $this->entitlements()->remainingIssuesThisWeek($user),
            'unlimited' => $this->entitlements()->hasUnlimitedIssues($user),
            'weeklyLimit' => $this->entitlements()->weeklyIssueLimit($user),
            'ideas' => $ideas,
        ]);
    }
}
