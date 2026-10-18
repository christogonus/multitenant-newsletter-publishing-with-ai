<?php

namespace App\Livewire\Concerns;

use App\Models\Newsletter;
use App\Models\User;
use App\Support\Entitlements;
use Livewire\Attributes\Computed;

/**
 * Shared helpers for dashboard components that work on the active publication.
 */
trait InteractsWithPublication
{
    #[Computed]
    public function newsletter(): Newsletter
    {
        $newsletter = $this->user()->activeNewsletter();

        abort_unless($newsletter, 404);

        return $newsletter;
    }

    protected function user(): User
    {
        return auth()->user();
    }

    protected function entitlements(): Entitlements
    {
        return app(Entitlements::class);
    }

    protected function toast(string $message, string $tone = 'success'): void
    {
        $this->dispatch('toast', message: $message, tone: $tone);
    }
}
