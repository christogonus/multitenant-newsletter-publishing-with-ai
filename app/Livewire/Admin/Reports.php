<?php

namespace App\Livewire\Admin;

use App\Models\Newsletter;
use App\Models\Report;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin: abuse reports, and the moderation flag that takes a page offline.
 */
#[Title('Admin · Reports')]
class Reports extends Component
{
    use WithPagination;

    #[Url]
    public bool $showResolved = false;

    public function resolve(int $reportId): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
        Report::query()->whereKey($reportId)->update(['resolved_at' => now()]);
    }

    public function takeOffline(int $newsletterId): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
        $newsletter = Newsletter::query()->findOrFail($newsletterId);
        $newsletter->update(['moderated_at' => now(), 'moderation_reason' => 'Taken offline after a report']);
        PublicPageCache::bust($newsletter);
        $this->dispatch('toast', message: $newsletter->name.' is offline.', tone: 'success');
    }

    public function restore(int $newsletterId): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
        $newsletter = Newsletter::query()->findOrFail($newsletterId);
        $newsletter->update(['moderated_at' => null, 'moderation_reason' => null]);
        PublicPageCache::bust($newsletter);
        $this->dispatch('toast', message: $newsletter->name.' is back online.', tone: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.reports', [
            'reports' => Report::query()
                ->with('newsletter:id,name,subdomain,moderated_at', 'issue:id,title,public_slug')
                ->when(! $this->showResolved, fn ($query) => $query->whereNull('resolved_at'))
                ->latest()
                ->paginate(25),
            'offline' => Newsletter::query()->whereNotNull('moderated_at')->get(['id', 'name', 'moderated_at']),
        ]);
    }
}
