<?php

namespace App\Livewire\Network;

use App\Enums\SwapStatus;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\NetworkListing;
use App\Models\NetworkSwap;
use App\Support\PublicPageCache;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Cross-promo network')]
class Index extends Component
{
    use InteractsWithPublication;

    public const CATEGORIES = [
        'business' => 'Business and careers',
        'money' => 'Money and investing',
        'tech' => 'Tech and AI',
        'health' => 'Health and fitness',
        'lifestyle' => 'Lifestyle and home',
        'family' => 'Family and parenting',
        'creative' => 'Creative and hobbies',
        'local' => 'Local and community',
        'other' => 'Something else',
    ];

    public string $category = 'business';

    public string $pitch = '';

    #[Url]
    public string $filter = '';

    public string $message = '';

    public function mount(): void
    {
        $listing = $this->newsletter->networkListing;

        if ($listing) {
            $this->category = $listing->category;
            $this->pitch = $listing->pitch;
        } else {
            $this->pitch = $this->newsletter->tagline ?: 'A newsletter about '.$this->newsletter->niche.'.';
        }
    }

    public function saveListing(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $this->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'pitch' => ['required', 'string', 'max:200'],
        ]);

        $newsletter->networkListing()->updateOrCreate([], [
            'category' => $this->category,
            'pitch' => $this->pitch,
            'audience_size' => $newsletter->activeSubscriberCount(),
            'is_listed' => true,
        ]);

        unset($this->newsletter);
        $this->toast('You are in the network. Other members can now find you.');
    }

    public function leave(): void
    {
        $listing = $this->myListing();
        $this->authorize('update', $listing);
        $listing->update(['is_listed' => false]);
        PublicPageCache::bust($this->newsletter);
        unset($this->newsletter);
        $this->toast('You are hidden from the network. Your existing swaps are paused.');
    }

    public function requestSwap(int $listingId): void
    {
        $mine = $this->myListing();
        $theirs = NetworkListing::query()->where('is_listed', true)->whereKeyNot($mine->id)->findOrFail($listingId);

        $this->validate(['message' => ['nullable', 'string', 'max:255']]);

        $alreadyLinked = NetworkSwap::query()
            ->where(fn ($query) => $query->where('requester_listing_id', $mine->id)->where('recipient_listing_id', $theirs->id))
            ->orWhere(fn ($query) => $query->where('requester_listing_id', $theirs->id)->where('recipient_listing_id', $mine->id))
            ->exists();

        if ($alreadyLinked) {
            $this->toast('You already have a swap with '.$theirs->newsletter->name.'.', 'info');

            return;
        }

        $swap = $mine->sentSwaps()->create([
            'recipient_listing_id' => $theirs->id,
            'message' => $this->message ?: null,
            'status' => $theirs->is_seeded ? SwapStatus::Accepted : SwapStatus::Pending,
            'responded_at' => $theirs->is_seeded ? now() : null,
        ]);

        if ($swap->status === SwapStatus::Accepted) {
            PublicPageCache::bust($this->newsletter);
        }

        $this->reset('message');
        $this->toast($swap->status === SwapStatus::Accepted
            ? 'Swap accepted. You now recommend each other.'
            : 'Request sent to '.$theirs->newsletter->name.'.');
    }

    public function respond(int $swapId, bool $accept): void
    {
        $mine = $this->myListing();
        $swap = $mine->receivedSwaps()->where('status', SwapStatus::Pending)->findOrFail($swapId);

        $swap->update(['status' => $accept ? SwapStatus::Accepted : SwapStatus::Declined, 'responded_at' => now()]);

        if ($accept) {
            PublicPageCache::bust($this->newsletter);
            PublicPageCache::bust($swap->requester->newsletter);
        }

        $this->toast($accept ? 'Swap accepted. You now recommend each other.' : 'Request declined.');
    }

    public function endSwap(int $swapId): void
    {
        $mine = $this->myListing();
        $swap = NetworkSwap::query()
            ->where(fn ($query) => $query->where('requester_listing_id', $mine->id)->orWhere('recipient_listing_id', $mine->id))
            ->findOrFail($swapId);

        $swap->delete();
        PublicPageCache::bust($this->newsletter);
        $this->toast('Swap ended.');
    }

    public function render(): View
    {
        $mine = $this->newsletter->networkListing;

        $directory = NetworkListing::query()
            ->with('newsletter:id,name,niche,subdomain,moderated_at')
            ->where('is_listed', true)
            ->whereHas('newsletter', fn ($query) => $query->visible())
            ->when($mine, fn ($query) => $query->whereKeyNot($mine->id))
            ->when($this->filter !== '', fn ($query) => $query->where('category', $this->filter))
            ->orderByDesc('audience_size')
            ->limit(60)
            ->get();

        return view('livewire.network.index', [
            'mine' => $mine,
            'directory' => $directory,
            'incoming' => $mine ? $mine->receivedSwaps()->where('status', SwapStatus::Pending)->with('requester.newsletter')->get() : collect(),
            'outgoing' => $mine ? $mine->sentSwaps()->where('status', SwapStatus::Pending)->with('recipient.newsletter')->get() : collect(),
            'partners' => $mine ? NetworkSwap::query()
                ->where('status', SwapStatus::Accepted)
                ->where(fn ($query) => $query->where('requester_listing_id', $mine->id)->orWhere('recipient_listing_id', $mine->id))
                ->with('requester.newsletter', 'recipient.newsletter')
                ->get() : collect(),
            'linkedIds' => $mine ? NetworkSwap::query()
                ->where('requester_listing_id', $mine->id)->pluck('recipient_listing_id')
                ->merge(NetworkSwap::query()->where('recipient_listing_id', $mine->id)->pluck('requester_listing_id'))
                ->all() : [],
            'categories' => self::CATEGORIES,
        ]);
    }

    private function myListing(): NetworkListing
    {
        $listing = $this->newsletter->networkListing;
        abort_unless($listing, 404);

        return $listing;
    }
}
