<?php

namespace App\Livewire\Subscribers;

use App\Actions\SubscribeReader;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Readers')]
class Index extends Component
{
    use InteractsWithPublication, WithPagination;

    #[Url]
    public string $search = '';

    public string $newEmail = '';

    public bool $hasPermission = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function add(SubscribeReader $subscribeReader): void
    {
        $this->authorize('update', $this->newsletter);

        $this->validate([
            'newEmail' => ['required', 'email:rfc', 'max:255'],
            'hasPermission' => ['accepted'],
        ], [
            'hasPermission.accepted' => 'Only add people who asked to get your newsletter.',
        ]);

        $subscribeReader->handle($this->newsletter, $this->newEmail, request()->ip(), source: 'added_by_owner');

        $this->reset('newEmail', 'hasPermission');
        $this->dispatch('close-modal', 'add-reader');
        $this->toast('Reader added.');
    }

    public function unsubscribe(int $subscriberId): void
    {
        $subscriber = Subscriber::query()->whereBelongsTo($this->newsletter)->findOrFail($subscriberId);
        $this->authorize('update', $subscriber);
        $subscriber->update(['unsubscribed_at' => now()]);
        $this->toast('Reader removed from your list.');
    }

    public function render(): View
    {
        $newsletter = $this->newsletter;

        return view('livewire.subscribers.index', [
            'subscribers' => $newsletter->subscribers()
                ->active()
                ->when($this->search !== '', fn ($query) => $query->where('email', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%'))
                ->withCount('referrals')
                ->latest()
                ->paginate(25),
            'total' => $newsletter->activeSubscriberCount(),
            'thisWeek' => $newsletter->subscribers()->active()->where('created_at', '>=', now()->subWeek())->count(),
            'daily' => $this->dailySignups(30),
            'sources' => $newsletter->subscribers()
                ->active()
                ->selectRaw('source, count(*) as total')
                ->groupBy('source')
                ->orderByDesc('total')
                ->pluck('total', 'source'),
        ]);
    }

    /**
     * Sign-ups per day for the last N days, oldest first, including days with none.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    private function dailySignups(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $counts = $this->newsletter->subscribers()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($since, $counts) {
                $date = $since->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('j M'),
                    'count' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            })
            ->all();
    }
}
