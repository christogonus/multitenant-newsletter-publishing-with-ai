<div class="space-y-6">
    <x-ui.page-header title="Readers" description="People who signed up on your page." icon="users" area="publish">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('subscribers.export')" icon="download">Download list</x-ui.button>
            <x-ui.button area="publish" icon="plus" x-on:click="$dispatch('open-modal', 'add-reader')">Add a reader</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Total readers" :value="number_format($total)" icon="users" area="publish" />
        <x-ui.stat-card label="New this week" :value="number_format($thisWeek)" icon="sparkles" area="grow" />
    </div>

    <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search by email" aria-label="Search readers" />

    <x-ui.table :headers="['Email', 'Joined from', 'Joined', 'Friends invited', '']" :empty="$subscribers->isEmpty()">
        <x-slot:emptyState>
            <x-ui.empty-state icon="users" area="publish" :title="$search ? 'No readers match that search' : 'No readers yet'" description="Share your page link and sign-ups appear here.">
                <x-ui.button :href="route('issues.index')" area="publish" icon="share">Find a link to share</x-ui.button>
            </x-ui.empty-state>
        </x-slot:emptyState>

        @foreach ($subscribers as $subscriber)
            <tr wire:key="subscriber-{{ $subscriber->id }}">
                <td class="px-4 py-3 font-medium text-ink">{{ $subscriber->email }}</td>
                <td class="px-4 py-3 text-muted">{{ str_replace('_', ' ', ucfirst($subscriber->source)) }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $subscriber->created_at->toFormattedDateString() }}</td>
                <td class="px-4 py-3 text-muted tabular-nums">{{ $subscriber->referrals_count }}</td>
                <td class="px-4 py-3 text-right">
                    <x-ui.button variant="ghost" size="sm" wire:click="unsubscribe({{ $subscriber->id }})" wire:confirm="Remove {{ $subscriber->email }} from your list?">Remove</x-ui.button>
                </td>
            </tr>
        @endforeach

        @if ($subscribers->hasPages())
            <x-slot:footer>{{ $subscribers->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.modal name="add-reader" title="Add a reader" max-width="md">
        <form wire:submit="add" class="space-y-4" id="add-reader-form">
            <x-ui.input wire:model="newEmail" type="email" label="Email" />
            <x-ui.checkbox wire:model="hasPermission" label="This person asked to get my newsletter." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button type="submit" form="add-reader-form" area="publish" loading="add">Add reader</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
