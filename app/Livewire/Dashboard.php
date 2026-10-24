<?php

namespace App\Livewire;

use App\Enums\IssueStatus;
use App\Enums\Product;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Newsletter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Home')]
class Dashboard extends Component
{
    use InteractsWithPublication;

    public function render(): View
    {
        $user = $this->user();
        $newsletter = $user->activeNewsletter();
        $entitlements = $this->entitlements();

        return view('livewire.dashboard', [
            'newsletter' => $newsletter,
            'checklist' => $newsletter ? $this->checklist($newsletter) : [],
            'remaining' => $newsletter ? $entitlements->remainingIssuesThisWeek($user, $newsletter) : 0,
            'unlimited' => $entitlements->hasUnlimitedIssues($user),
            'weeklyLimit' => $entitlements->weeklyIssueLimit($user),
            'readers' => $newsletter?->activeSubscriberCount() ?? 0,
            'liveIssues' => $newsletter?->issues()->where('status', IssueStatus::Published)->count() ?? 0,
            'views' => (int) ($newsletter?->issues()->sum('views') ?? 0),
            'recentIssues' => $newsletter?->issues()->latest()->limit(5)->get() ?? collect(),
            'offer' => collect(Product::cases())
                ->reject(fn (Product $product) => in_array($product, [Product::FrontEnd, Product::BumpVault], true) || $user->owns($product))
                ->first(),
        ]);
    }

    /**
     * Hides the getting-started checklist for the active newsletter.
     */
    public function dismissChecklist(): void
    {
        $newsletter = $this->user()->activeNewsletter();

        if ($newsletter) {
            $newsletter->forceFill(['checklist_dismissed_at' => now()])->save();
        }
    }

    /**
     * The four-step getting-started checklist.
     *
     * @return list<array{label: string, done: bool, route: string, action: string}>
     */
    private function checklist(Newsletter $newsletter): array
    {
        $firstIssue = $newsletter->issues()->oldest()->first();
        $hasDraft = $newsletter->issues()->whereIn('status', [IssueStatus::Draft, IssueStatus::Published, IssueStatus::Scheduled])->exists();
        $published = $newsletter->issues()->where('status', IssueStatus::Published)->exists();

        return [
            ['label' => 'Pick your niche', 'done' => true, 'route' => route('newsletters.settings'), 'action' => 'Edit'],
            ['label' => 'Review your first issue', 'done' => $hasDraft, 'route' => $firstIssue ? route('issues.edit', $firstIssue) : route('issues.create'), 'action' => 'Review'],
            ['label' => 'Publish it', 'done' => $published, 'route' => $firstIssue ? route('issues.edit', $firstIssue) : route('issues.create'), 'action' => 'Publish'],
            ['label' => 'Share your link', 'done' => $published && ($newsletter->link_shared_at !== null || $newsletter->subscribers()->exists()), 'route' => $published ? route('issues.share', $newsletter->issues()->where('status', IssueStatus::Published)->latest('published_at')->first()) : route('issues.index'), 'action' => 'Share'],
        ];
    }
}
