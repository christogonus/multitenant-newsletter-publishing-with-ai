<?php

namespace App\Livewire\Publications;

use App\Enums\EarningSource;
use App\Enums\IssueStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Earning;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * OTO 9: every newsletter on one dashboard, with its readers, live issues and the money it has brought in.
 */
#[Title('All publications')]
class Index extends Component
{
    use InteractsWithPublication;

    public ?int $earningNewsletterId = null;

    public string $earningSource = 'sponsor';

    public string $earningDescription = '';

    public string $earningAmount = '';

    public string $earningDate = '';

    public function switchTo(int $newsletterId): void
    {
        $newsletter = $this->user()->newsletters()->findOrFail($newsletterId);
        $this->authorize('update', $newsletter);

        $this->user()->forceFill(['current_newsletter_id' => $newsletter->id])->save();
        $this->redirectRoute('dashboard');
    }

    public function openEarningForm(?int $newsletterId = null): void
    {
        $this->reset('earningSource', 'earningDescription', 'earningAmount');
        $this->resetValidation();
        $this->earningNewsletterId = $newsletterId ?? $this->user()->activeNewsletter()?->id;
        $this->earningDate = now()->toDateString();
        $this->dispatch('open-modal', 'earning');
    }

    public function logEarning(): void
    {
        $validated = $this->validate([
            'earningNewsletterId' => ['required', 'integer'],
            'earningSource' => ['required', Rule::enum(EarningSource::class)],
            'earningDescription' => ['required', 'string', 'max:120'],
            'earningAmount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'earningDate' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'earningDescription.required' => 'Say who paid, for example the sponsor\'s name.',
            'earningAmount.min' => 'Enter an amount above zero.',
            'earningDate.before_or_equal' => 'Log money once it has come in.',
        ]);

        $newsletter = $this->user()->newsletters()->findOrFail($validated['earningNewsletterId']);
        $this->authorize('update', $newsletter);

        $newsletter->earnings()->create([
            'source' => $validated['earningSource'],
            'description' => trim($validated['earningDescription']),
            'amount' => $validated['earningAmount'],
            'earned_on' => $validated['earningDate'],
        ]);

        $this->dispatch('close-modal', 'earning');
        $this->toast('Logged $'.number_format((float) $validated['earningAmount'], 2).' for '.$newsletter->name.'.');
    }

    public function deleteEarning(int $earningId): void
    {
        $earning = Earning::query()->whereIn('newsletter_id', $this->user()->newsletters()->select('id'))->findOrFail($earningId);
        $this->authorize('delete', $earning);

        $earning->delete();
        $this->toast('Removed.');
    }

    public function render(): View
    {
        $user = $this->user();
        $monthStart = now()->startOfMonth()->toDateString();

        $publications = $user->newsletters()
            ->with('domain')
            ->withCount([
                'subscribers' => fn ($query) => $query->whereNull('unsubscribed_at'),
                'issues as live_issues_count' => fn ($query) => $query->where('status', IssueStatus::Published),
            ])
            ->withSum('earnings as earned_total', 'amount')
            ->withSum(['earnings as earned_this_month' => fn ($query) => $query->where('earned_on', '>=', $monthStart)], 'amount')
            ->orderBy('name')
            ->get();

        return view('livewire.publications.index', [
            'publications' => $publications,
            'currentId' => $user->activeNewsletter()?->id,
            'canCreate' => $this->entitlements()->canCreatePublication($user),
            'totalReaders' => $publications->sum('subscribers_count'),
            'earnedThisMonth' => (float) $publications->sum('earned_this_month'),
            'earnedTotal' => (float) $publications->sum('earned_total'),
            'recentEarnings' => Earning::query()
                ->whereIn('newsletter_id', $publications->pluck('id'))
                ->with('newsletter:id,name')
                ->latest('earned_on')
                ->latest('id')
                ->limit(10)
                ->get(),
            'sources' => collect(EarningSource::cases())->mapWithKeys(fn (EarningSource $source) => [$source->value => $source->label()])->all(),
            'newsletterOptions' => $publications->pluck('name', 'id')->all(),
        ]);
    }
}
