<?php

namespace App\Livewire\Issues;

use App\Enums\IssueStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Newsletters')]
class Index extends Component
{
    use InteractsWithPublication, WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->search)).'%';

        $issues = $this->newsletter->issues()
            ->when($this->status !== 'all' && IssueStatus::tryFrom($this->status), fn ($query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('subject_line', 'like', $term)))
            ->latest()
            ->paginate(15);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'filters' => [
                'all' => 'All',
                IssueStatus::Draft->value => 'Drafts',
                IssueStatus::Scheduled->value => 'Scheduled',
                IssueStatus::Published->value => 'Live',
            ],
            'hasGenerating' => $issues->contains(fn ($issue) => $issue->status === IssueStatus::Generating),
        ]);
    }
}
