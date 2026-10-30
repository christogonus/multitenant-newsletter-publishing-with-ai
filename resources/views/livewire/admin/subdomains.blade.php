<div class="space-y-6">
    <x-ui.page-header title="Reserved addresses" description="Subdomains no publication can claim." icon="globe">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.content')">Content</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert>
        Every new web address needs at least {{ \App\Actions\CreateNewsletter::SUBDOMAIN_MIN_LENGTH }} characters and cannot be one of the names below.
        Reserving a name does not take it from a publication that already uses it.
    </x-ui.alert>

    <x-ui.card title="Reserve an address">
        <form wire:submit="add" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex-1">
                <x-ui.input wire:model="name" placeholder="for example, billing" aria-label="Subdomain to reserve" :hint="'Blocks name.'.$publicRoot" />
            </div>
            <x-ui.button type="submit" icon="plus">Reserve</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.table :headers="['Added by admins', '']" :empty="$added->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="globe" title="None added yet" description="Names you reserve show up here." /></x-slot:emptyState>
        @foreach ($added as $reserved)
            <tr wire:key="reserved-{{ $reserved->id }}">
                <td class="px-4 py-3 font-mono text-sm text-ink">{{ $reserved->name }}.{{ $publicRoot }}</td>
                <td class="px-4 py-3 text-right">
                    <x-ui.button variant="ghost" size="sm" icon="trash" wire:click="remove({{ $reserved->id }})" wire:confirm="Let publications use {{ $reserved->name }} again?">Remove</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.card title="Built in" description="Always reserved. Change these in config/newspub.php.">
        <div class="flex flex-wrap gap-2">
            @foreach ($builtIn as $name)
                <x-ui.pill wire:key="built-in-{{ $name }}" class="font-mono">{{ $name }}</x-ui.pill>
            @endforeach
        </div>
    </x-ui.card>
</div>
