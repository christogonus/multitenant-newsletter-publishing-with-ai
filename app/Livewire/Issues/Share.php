<?php

namespace App\Livewire\Issues;

use App\Enums\Product;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Issue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Your newsletter is live')]
class Share extends Component
{
    use InteractsWithPublication;

    public Issue $issue;

    public function mount(Issue $issue): void
    {
        $this->authorize('view', $issue);
        abort_unless($issue->isPublished(), 404);
        $this->issue = $issue;

        // Seeing the share links completes the "Share your link" step on Home.
        $issue->newsletter->link_shared_at ??= now();
        $issue->newsletter->save();
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.issues.share', [
            'url' => $this->issue->publicUrl(),
            'homeUrl' => $this->issue->newsletter->publicUrl(),
            'hasDelivery' => $user->owns(Product::Oto1Delivery),
            'hasSocial' => $user->owns(Product::Oto6Social),
        ]);
    }
}
