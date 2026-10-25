<?php

namespace App\Livewire\Growth;

use App\Ai\Contracts\NewsletterWriter;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Subscriber;
use App\Support\Blocks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Sign-up pages and referrals')]
class Index extends Component
{
    use InteractsWithPublication;

    public string $giftIdea = '';

    /** @var list<array{referrals: int|string, reward: string}> */
    public array $tiers = [];

    public function mount(): void
    {
        $this->tiers = $this->newsletter->reward_tiers ?? [
            ['referrals' => 3, 'reward' => 'A shout-out in the next newsletter'],
            ['referrals' => 10, 'reward' => 'Our bonus guide'],
            ['referrals' => 25, 'reward' => 'A 20-minute call with me'],
        ];
    }

    public function createLeadPage(NewsletterWriter $writer): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);
        $this->validate(['giftIdea' => ['nullable', 'string', 'max:200']]);

        if (RateLimiter::tooManyAttempts('lead-page:'.$this->user()->id, 10)) {
            $this->toast('You have made a lot of pages today. Try again tomorrow.', 'warning');

            return;
        }

        RateLimiter::hit('lead-page:'.$this->user()->id, 86400);

        try {
            $draft = $writer->leadPage($newsletter, trim($this->giftIdea) ?: null);
        } catch (Throwable $exception) {
            report($exception);
            $this->toast('The writer could not finish. Please try again.', 'danger');

            return;
        }

        $blocks = [];
        foreach ($draft['magnet_sections'] as $section) {
            $blocks[] = ['type' => 'heading', 'text' => $section['heading']];
            foreach (preg_split('/\n\s*\n/', $section['body']) ?: [] as $paragraph) {
                $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
            }
        }

        $leadPage = $newsletter->leadPages()->create([
            'slug' => $this->uniqueSlug($draft['magnet_title'] ?: 'free-gift'),
            'headline' => Str::limit($draft['headline'] ?: 'Get the free guide', 250, ''),
            'subheadline' => Str::limit($draft['subheadline'], 250, '') ?: null,
            'bullets' => array_slice(array_filter($draft['bullets']), 0, 6),
            'button_label' => Str::limit($draft['button_label'] ?: 'Send it to me', 60, ''),
            'magnet_title' => Str::limit($draft['magnet_title'] ?: 'Your free guide', 250, ''),
            'magnet_blocks' => Blocks::normalize($blocks),
        ]);

        $this->redirectRoute('growth.pages.edit', $leadPage);
    }

    public function addTier(): void
    {
        $this->tiers[] = ['referrals' => '', 'reward' => ''];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    public function saveTiers(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->validate([
            'tiers' => ['array', 'max:8'],
            'tiers.*.referrals' => ['required', 'integer', 'min:1', 'max:100000'],
            'tiers.*.reward' => ['required', 'string', 'max:160'],
        ], [
            'tiers.*.referrals.required' => 'How many friends?',
            'tiers.*.reward.required' => 'What do they get?',
        ]);

        $tiers = collect($this->tiers)
            ->map(fn (array $tier) => ['referrals' => (int) $tier['referrals'], 'reward' => trim($tier['reward'])])
            ->sortBy('referrals')
            ->values()
            ->all();

        $newsletter->update(['reward_tiers' => $tiers]);
        $this->tiers = $tiers;
        $this->toast('Rewards saved. Readers see them after they subscribe.');
    }

    public function render(): View
    {
        $newsletter = $this->newsletter;

        return view('livewire.growth.index', [
            'leadPages' => $newsletter->leadPages()->withCount('subscribers')->latest()->get(),
            'topReferrers' => Subscriber::query()
                ->whereBelongsTo($newsletter)
                ->active()
                ->withCount(['referrals' => fn ($query) => $query->whereNull('unsubscribed_at')])
                ->whereHas('referrals', fn ($query) => $query->whereNull('unsubscribed_at'))
                ->orderByDesc('referrals_count')
                ->limit(10)
                ->get(),
            'referredTotal' => $newsletter->subscribers()->active()->whereNotNull('referred_by_id')->count(),
            'playbook' => config('playbook'),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 60, '') ?: 'free-gift';
        $slug = $base;
        $suffix = 2;

        while ($this->newsletter->leadPages()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
