<div class="space-y-6">
    <x-ui.page-header title="Cross-promo network" description="Team up with other newsletters and recommend each other on your pages." icon="swap" area="grow" />

    <x-ui.card :title="$mine ? 'Your listing' : 'Join the network'" description="This is what other members see.">
        <form wire:submit="saveListing" class="grid gap-4 sm:grid-cols-[14rem_1fr_auto] sm:items-start">
            <x-ui.select wire:model="category" label="Category" :options="$categories" />
            <x-ui.input wire:model="pitch" label="One-line pitch" />
            <x-ui.button type="submit" area="grow" class="sm:mt-7" loading="saveListing">{{ $mine ? 'Save' : 'Join' }}</x-ui.button>
        </form>
        @if ($mine)
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <x-ui.pill :tone="$mine->is_listed ? 'success' : 'neutral'">{{ $mine->is_listed ? 'Listed' : 'Hidden' }}</x-ui.pill>
                <span class="text-muted">{{ number_format($mine->audience_size) }} readers shown</span>
                @if ($mine->is_listed)
                    <x-ui.button variant="ghost" size="sm" wire:click="leave">Hide me</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.card>

    @if ($mine)
        @if ($incoming->isNotEmpty())
            <x-ui.card title="Requests for you">
                <ul class="divide-y divide-line">
                    @foreach ($incoming as $swap)
                        <li wire:key="incoming-{{ $swap->id }}" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-ink">{{ $swap->requester->newsletter->name }}</p>
                                <p class="text-sm text-muted">{{ $swap->message ?: $swap->requester->pitch }}</p>
                            </div>
                            <x-ui.button variant="ghost" size="sm" wire:click="respond({{ $swap->id }}, false)">Decline</x-ui.button>
                            <x-ui.button area="grow" size="sm" wire:click="respond({{ $swap->id }}, true)">Accept</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card title="Your partners" description="Up to three partners appear on your page. The most active ones are shown first.">
            @if ($partners->isEmpty())
                <x-ui.empty-state icon="swap" area="grow" title="No partners yet" description="Send a request to a newsletter below. When they accept, you recommend each other." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($partners as $swap)
                        @php $partner = $swap->requester_listing_id === $mine->id ? $swap->recipient : $swap->requester; @endphp
                        <li wire:key="partner-{{ $swap->id }}" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-ink">{{ $partner->newsletter->name }}</p>
                                <p class="text-sm text-muted">{{ $partner->pitch }}</p>
                            </div>
                            <x-ui.button variant="ghost" size="sm" wire:click="endSwap({{ $swap->id }})" wire:confirm="End this swap?">End</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($outgoing->isNotEmpty())
                <p class="mt-4 text-sm text-muted">Waiting on: {{ $outgoing->map(fn ($swap) => $swap->recipient->newsletter->name)->implode(', ') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Find partners">
            <x-ui.select wire:model.live="filter" label="Category" :options="$categories" placeholder="All categories" class="mb-5 sm:max-w-xs" />
            @if ($directory->isEmpty())
                <x-ui.empty-state icon="search" area="grow" title="Nobody in this category yet" description="Try another category." />
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($directory as $listing)
                        <div wire:key="listing-{{ $listing->id }}" class="rounded-card border border-line p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $listing->newsletter->name }}</p>
                                    <p class="text-xs text-muted">{{ $categories[$listing->category] ?? $listing->category }} · {{ number_format($listing->audience_size) }} readers</p>
                                </div>
                                @if ($listing->is_seeded)<x-ui.badge tone="grow">Featured</x-ui.badge>@endif
                            </div>
                            <p class="mt-2 text-sm text-muted">{{ $listing->pitch }}</p>
                            <div class="mt-3 flex justify-end">
                                @if (in_array($listing->id, $linkedIds, true))
                                    <x-ui.badge tone="success" icon="check">Linked</x-ui.badge>
                                @else
                                    <x-ui.button variant="secondary" size="sm" wire:click="requestSwap({{ $listing->id }})" loading="requestSwap({{ $listing->id }})">Ask to swap</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
