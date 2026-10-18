<?php

namespace App\Livewire\Sponsors;

use App\Ai\Contracts\NewsletterWriter;
use App\Enums\IssueStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Sponsor;
use App\Support\RateGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Title('Sponsors')]
class Index extends Component
{
    use InteractsWithPublication;

    #[Url]
    public string $tab = 'directory';

    public string $kind = '';

    public ?int $audienceSize = null;

    public function mount(): void
    {
        $this->audienceSize = $this->newsletter->activeSubscriberCount();
    }

    public function generateMediaKit(NewsletterWriter $writer): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $key = 'media-kit:'.$this->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->toast('You have made several media kits today. Try again tomorrow.', 'warning');

            return;
        }

        RateLimiter::hit($key, 86400);

        try {
            $content = $writer->mediaKit($newsletter, [
                'subscribers' => $newsletter->activeSubscriberCount(),
                'issues' => $newsletter->issues()->where('status', IssueStatus::Published)->count(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->toast('The writer could not finish your media kit. Please try again.', 'danger');

            return;
        }

        $newsletter->mediaKit()->updateOrCreate([], ['content' => $content]);
        unset($this->newsletter);
        $this->tab = 'media-kit';
        $this->toast('Your media kit is ready.');
    }

    public function render(RateGuide $rateGuide): View
    {
        $audience = max(0, (int) $this->audienceSize);

        return view('livewire.sponsors.index', [
            'sponsors' => Sponsor::query()->where('is_active', true)->when($this->kind !== '', fn ($query) => $query->where('kind', $this->kind))->orderBy('kind')->orderBy('name')->get(),
            'kinds' => Sponsor::query()->where('is_active', true)->distinct()->orderBy('kind')->pluck('kind'),
            'mediaKit' => $this->newsletter->mediaKit,
            'packages' => $rateGuide->packages($audience),
            'assumptions' => $rateGuide->assumptions($audience),
            'outreach' => config('sponsors.outreach'),
            'paidGuide' => config('sponsors.paid_guide'),
            'tabs' => ['directory' => 'Find sponsors', 'media-kit' => 'Media kit', 'outreach' => 'Pitch emails', 'rates' => 'What to charge', 'paid' => 'Paid subscriptions'],
        ]);
    }
}
