<?php

namespace App\Livewire\Social;

use App\Ai\Contracts\NewsletterWriter;
use App\Enums\IssueStatus;
use App\Enums\SocialChannel;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Issue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Title('Social posts')]
class Index extends Component
{
    use InteractsWithPublication;

    public const DAILY_LIMIT = 60;

    #[Url]
    public ?int $issue = null;

    public function mount(): void
    {
        if ($this->issue && ! $this->selectedIssue()) {
            $this->issue = null;
        }

        $this->issue ??= $this->newsletter->issues()->whereIn('status', [IssueStatus::Published, IssueStatus::Draft, IssueStatus::Scheduled])->latest()->value('id');
    }

    public function generate(string $channel, NewsletterWriter $writer): void
    {
        $issue = $this->selectedIssue();
        abort_unless($issue, 404);
        $this->authorize('update', $issue);

        $channel = SocialChannel::from($channel);
        $key = 'social:'.$this->user()->id;

        if (RateLimiter::tooManyAttempts($key, self::DAILY_LIMIT)) {
            $this->toast('You have made a lot of posts today. Try again tomorrow.', 'warning');

            return;
        }

        RateLimiter::hit($key, 86400);

        try {
            $body = $writer->repurpose($issue, $channel);
        } catch (Throwable $exception) {
            report($exception);
            $this->toast('The writer could not finish. Please try again.', 'danger');

            return;
        }

        $issue->socialPosts()->create(['channel' => $channel, 'body' => $body]);
        $this->toast($channel->label().' ready to copy.');
    }

    public function generateAll(NewsletterWriter $writer): void
    {
        foreach (SocialChannel::cases() as $channel) {
            $this->generate($channel->value, $writer);
        }
    }

    public function deletePost(int $postId): void
    {
        $issue = $this->selectedIssue();
        abort_unless($issue, 404);
        $this->authorize('update', $issue);
        $issue->socialPosts()->whereKey($postId)->delete();
    }

    public function render(): View
    {
        $issue = $this->selectedIssue();

        return view('livewire.social.index', [
            'issues' => $this->newsletter->issues()->whereIn('status', [IssueStatus::Published, IssueStatus::Draft, IssueStatus::Scheduled])->latest()->limit(50)->pluck('title', 'id'),
            'selected' => $issue,
            'posts' => $issue?->socialPosts()->latest()->get()->groupBy(fn ($post) => $post->channel->value) ?? collect(),
            'channels' => SocialChannel::cases(),
        ]);
    }

    private function selectedIssue(): ?Issue
    {
        return $this->issue ? $this->newsletter->issues()->whereKey($this->issue)->first() : null;
    }
}
