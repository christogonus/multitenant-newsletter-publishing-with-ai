<?php

namespace App\Livewire\Publications;

use App\Enums\IssueStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('All publications')]
class Index extends Component
{
    use InteractsWithPublication;

    public function switchTo(int $newsletterId): void
    {
        $newsletter = $this->user()->newsletters()->findOrFail($newsletterId);
        $this->authorize('update', $newsletter);

        $this->user()->forceFill(['current_newsletter_id' => $newsletter->id])->save();
        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.publications.index', [
            'publications' => $user->newsletters()
                ->with('domain')
                ->withCount([
                    'subscribers' => fn ($query) => $query->whereNull('unsubscribed_at'),
                    'issues as live_issues_count' => fn ($query) => $query->where('status', IssueStatus::Published),
                ])
                ->orderBy('name')
                ->get(),
            'currentId' => $user->activeNewsletter()?->id,
            'canCreate' => $this->entitlements()->canCreatePublication($user),
            'totalReaders' => $user->newsletters()->withCount(['subscribers' => fn ($query) => $query->whereNull('unsubscribed_at')])->get()->sum('subscribers_count'),
        ]);
    }
}
