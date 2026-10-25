<div class="space-y-6">
    <x-ui.page-header title="Content" description="Check the hand-made content buyers rely on before launch, and keep it current." icon="archive">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.spend')">AI spend</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div role="tablist" class="flex gap-1 rounded-control bg-subtle p-1 sm:w-fit">
        @foreach (['sponsors' => 'Sponsor directory', 'network' => 'Network featured listings'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class(['min-h-11 rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap', 'bg-card text-ink shadow-card' => $tab === $key, 'text-muted hover:text-ink' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'sponsors')
        @if ($unchecked > 0)
            <x-ui.alert tone="warning" title="{{ $unchecked }} {{ \Illuminate\Support\Str::plural('entry', $unchecked) }} not checked yet">
                Open each website, confirm the programme is real and still taking publications, then press "Mark checked". Hide any that are not.
            </x-ui.alert>
        @endif

        <div class="flex justify-end">
            <x-ui.button wire:click="editSponsor" icon="plus">Add an entry</x-ui.button>
        </div>

        <x-ui.table :headers="['Entry', 'Type', 'Checked', 'Shown', '']" :empty="$sponsors->isEmpty()">
            <x-slot:emptyState><x-ui.empty-state icon="currency-dollar" area="earn" title="No entries yet" description="Run the sponsor seeder or add one." /></x-slot:emptyState>
            @foreach ($sponsors as $entry)
                <tr wire:key="sponsor-{{ $entry->id }}">
                    <td class="px-4 py-3">
                        <a href="{{ $entry->website }}" target="_blank" rel="noopener" class="font-medium text-brand hover:underline">{{ $entry->name }}</a>
                        <p class="max-w-md truncate text-xs text-muted">{{ $entry->description }}</p>
                    </td>
                    <td class="px-4 py-3 text-muted">{{ $entry->kind }}</td>
                    <td class="px-4 py-3">
                        @if ($entry->verified_at)
                            <x-ui.pill tone="success">{{ $entry->verified_at->toFormattedDateString() }}</x-ui.pill>
                        @else
                            <x-ui.pill tone="warning">Not checked</x-ui.pill>
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-ui.pill :tone="$entry->is_active ? 'success' : 'neutral'">{{ $entry->is_active ? 'Shown' : 'Hidden' }}</x-ui.pill></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @unless ($entry->verified_at)
                            <x-ui.button variant="secondary" size="sm" wire:click="markChecked({{ $entry->id }})" icon="check">Mark checked</x-ui.button>
                        @endunless
                        <x-ui.button variant="ghost" size="sm" wire:click="toggleSponsor({{ $entry->id }})">{{ $entry->is_active ? 'Hide' : 'Show' }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="editSponsor({{ $entry->id }})">Edit</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @else
        <x-ui.alert>Featured listings are the house publications that make the directory feel busy at launch. Swaps with them are accepted automatically. Replace the placeholders with real publications you run.</x-ui.alert>

        <x-ui.table :headers="['Publication', 'Category', 'Swaps', 'Shown', '']" :empty="$listings->isEmpty()">
            <x-slot:emptyState><x-ui.empty-state icon="swap" area="grow" title="No featured listings" description="Run the network seeder to add them." /></x-slot:emptyState>
            @foreach ($listings as $listing)
                <tr wire:key="listing-{{ $listing->id }}">
                    <td class="px-4 py-3">
                        <a href="{{ $listing->newsletter->publicUrl() }}" target="_blank" rel="noopener" class="font-medium text-brand hover:underline">{{ $listing->newsletter->name }}</a>
                        <p class="max-w-md truncate text-xs text-muted">{{ $listing->pitch }}</p>
                    </td>
                    <td class="px-4 py-3 text-muted">{{ $categories[$listing->category] ?? $listing->category }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ $listing->sent_swaps_count + $listing->received_swaps_count }}</td>
                    <td class="px-4 py-3"><x-ui.pill :tone="$listing->is_listed ? 'success' : 'neutral'">{{ $listing->is_listed ? 'Shown' : 'Hidden' }}</x-ui.pill></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <x-ui.button variant="ghost" size="sm" wire:click="toggleListing({{ $listing->id }})">{{ $listing->is_listed ? 'Hide' : 'Show' }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="editListing({{ $listing->id }})">Edit</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.slide-over name="sponsor-form" :title="$editingSponsorId ? 'Edit entry' : 'Add an entry'">
        <form wire:submit="saveSponsor" id="sponsor-form" class="space-y-4">
            <x-ui.input wire:model="sponsor.name" label="Name" />
            <x-ui.input wire:model="sponsor.kind" label="Type" hint="For example Ad network, Marketplace, Directory, Affiliate network." />
            <x-ui.input wire:model="sponsor.website" type="url" label="Website" placeholder="https://" />
            <x-ui.textarea wire:model="sponsor.description" label="Description" rows="3" />
            <x-ui.input wire:model="sponsor.niches" label="Good for" hint="Comma separated, or leave empty for any niche." optional />
            <x-ui.input wire:model="sponsor.minimum_audience" label="Minimum readers" optional />
            <p class="text-sm text-muted">Saving marks the entry as checked today.</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button type="submit" form="sponsor-form" loading="saveSponsor">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.slide-over>

    <x-ui.modal name="listing-form" title="Edit featured listing" max-width="md">
        <div class="space-y-4">
            <x-ui.select wire:model="listingCategory" label="Category" :options="$categories" />
            <x-ui.input wire:model="listingPitch" label="Pitch" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button wire:click="saveListing" loading="saveListing">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
