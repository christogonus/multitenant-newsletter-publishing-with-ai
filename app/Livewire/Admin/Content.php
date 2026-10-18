<?php

namespace App\Livewire\Admin;

use App\Livewire\Network\Index as Network;
use App\Models\NetworkListing;
use App\Models\Sponsor;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin: curate the hand-made content buyers see. The launch checklist requires every sponsor and
 * ad-network entry to be re-checked as real and active, and the network seed to be real publications.
 */
#[Title('Admin · Content')]
class Content extends Component
{
    #[Url]
    public string $tab = 'sponsors';

    public ?int $editingSponsorId = null;

    /** @var array{name: string, kind: string, website: string, description: string, niches: string, minimum_audience: string} */
    public array $sponsor = ['name' => '', 'kind' => '', 'website' => '', 'description' => '', 'niches' => '', 'minimum_audience' => ''];

    public ?int $editingListingId = null;

    public string $listingCategory = 'business';

    public string $listingPitch = '';

    public function editSponsor(?int $sponsorId = null): void
    {
        $this->authorizeAdmin();
        $this->resetValidation();
        $existing = $sponsorId ? Sponsor::query()->findOrFail($sponsorId) : null;

        $this->editingSponsorId = $existing?->id;
        $this->sponsor = [
            'name' => (string) $existing?->name,
            'kind' => (string) $existing?->kind,
            'website' => (string) $existing?->website,
            'description' => (string) $existing?->description,
            'niches' => implode(', ', $existing?->niches ?? []),
            'minimum_audience' => (string) $existing?->minimum_audience,
        ];

        $this->dispatch('open-slide-over', 'sponsor-form');
    }

    public function saveSponsor(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'sponsor.name' => ['required', 'string', 'max:120'],
            'sponsor.kind' => ['required', 'string', 'max:60'],
            'sponsor.website' => ['required', 'url:https', 'max:255'],
            'sponsor.description' => ['required', 'string', 'max:1000'],
            'sponsor.niches' => ['nullable', 'string', 'max:255'],
            'sponsor.minimum_audience' => ['nullable', 'string', 'max:100'],
        ])['sponsor'];

        Sponsor::query()->updateOrCreate(['id' => $this->editingSponsorId], [
            ...$validated,
            'niches' => array_values(array_filter(array_map('trim', explode(',', (string) $validated['niches'])))) ?: ['Any'],
            'verified_at' => now(),
        ]);

        $this->dispatch('close-slide-over', 'sponsor-form');
        $this->dispatch('toast', message: 'Saved and marked as checked today.', tone: 'success');
    }

    public function markChecked(int $sponsorId): void
    {
        $this->authorizeAdmin();
        Sponsor::query()->whereKey($sponsorId)->update(['verified_at' => now()]);
    }

    public function toggleSponsor(int $sponsorId): void
    {
        $this->authorizeAdmin();
        $sponsor = Sponsor::query()->findOrFail($sponsorId);
        $sponsor->update(['is_active' => ! $sponsor->is_active]);
    }

    public function editListing(int $listingId): void
    {
        $this->authorizeAdmin();
        $listing = NetworkListing::query()->findOrFail($listingId);
        $this->editingListingId = $listing->id;
        $this->listingCategory = $listing->category;
        $this->listingPitch = $listing->pitch;
        $this->dispatch('open-modal', 'listing-form');
    }

    public function saveListing(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'listingCategory' => ['required', Rule::in(array_keys(Network::CATEGORIES))],
            'listingPitch' => ['required', 'string', 'max:200'],
        ]);

        $listing = NetworkListing::query()->findOrFail($this->editingListingId);
        $listing->update(['category' => $this->listingCategory, 'pitch' => $this->listingPitch]);

        $this->dispatch('close-modal', 'listing-form');
        $this->dispatch('toast', message: 'Listing saved.', tone: 'success');
    }

    public function toggleListing(int $listingId): void
    {
        $this->authorizeAdmin();
        $listing = NetworkListing::query()->with('newsletter')->findOrFail($listingId);
        $listing->update(['is_listed' => ! $listing->is_listed]);
        PublicPageCache::bust($listing->newsletter);
    }

    public function render(): View
    {
        return view('livewire.admin.content', [
            'sponsors' => Sponsor::query()->orderByRaw('verified_at is null desc')->orderBy('name')->get(),
            'listings' => NetworkListing::query()->where('is_seeded', true)->with('newsletter:id,name,subdomain', 'newsletter.domain')->withCount(['sentSwaps', 'receivedSwaps'])->orderBy('category')->get(),
            'categories' => Network::CATEGORIES,
            'unchecked' => Sponsor::query()->where('is_active', true)->whereNull('verified_at')->count(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
