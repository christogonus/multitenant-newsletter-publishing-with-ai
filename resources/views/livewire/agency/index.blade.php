<div class="space-y-6">
    <x-ui.page-header title="Agency clients" description="Run publications for paying clients, with ready packages and contracts." icon="briefcase" area="earn">
        <x-slot:actions>
            <x-ui.button wire:click="create" area="earn" icon="plus" size="lg">Add a client</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Clients" :value="$clients->count()" icon="briefcase" area="earn" />
        <x-ui.stat-card label="Monthly income from signed clients" :value="'$'.number_format($monthlyRevenue, 2)" icon="currency-dollar" area="earn" />
    </div>

    <x-ui.table :headers="['Client', 'Package', 'Monthly fee', 'Contract', 'Publications', '']" :empty="$clients->isEmpty()">
        <x-slot:emptyState>
            <x-ui.empty-state icon="briefcase" area="earn" title="No clients yet" description="Add your first client, pick a package and send them the contract.">
                <x-ui.button wire:click="create" area="earn" icon="plus">Add a client</x-ui.button>
            </x-ui.empty-state>
        </x-slot:emptyState>
        @foreach ($clients as $client)
            <tr wire:key="client-{{ $client->id }}">
                <td class="px-4 py-3"><p class="font-medium text-ink">{{ $client->name }}</p><p class="text-xs text-muted">{{ $client->contact_email }}</p></td>
                <td class="px-4 py-3 text-muted">{{ collect($packages)->firstWhere('key', $client->package)['name'] ?? $client->package }}</td>
                <td class="px-4 py-3 text-ink tabular-nums">${{ number_format((float) $client->monthly_fee, 2) }}</td>
                <td class="px-4 py-3"><x-ui.pill :tone="match ($client->contract_status) { \App\Enums\ContractStatus::Signed => 'success', \App\Enums\ContractStatus::Sent => 'warning', \App\Enums\ContractStatus::Ended => 'neutral', default => 'neutral' }">{{ $client->contract_status->label() }}</x-ui.pill></td>
                <td class="px-4 py-3 text-muted">{{ $client->newsletters->pluck('name')->implode(', ') ?: '—' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <x-ui.button variant="ghost" size="sm" wire:click="showContract({{ $client->id }})" icon="document-text">Contract</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" wire:click="edit({{ $client->id }})">Edit</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.card title="Packages you can offer">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($packages as $package)
                <div class="rounded-card border border-line p-5">
                    <p class="text-sm font-semibold text-ink">{{ $package['name'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-ink">${{ number_format($package['monthly_fee']) }}<span class="text-sm font-medium text-muted"> / month</span></p>
                    <p class="mt-1 text-sm text-muted">{{ $package['issues_per_month'] }} newsletters a month · {{ $package['best_for'] }}</p>
                    <ul class="mt-3 space-y-1 text-sm text-ink">
                        @foreach ($package['includes'] as $include)
                            <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />{{ $include }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mt-5">
            <p class="text-sm font-semibold text-ink">Pricing tips</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-muted">
                @foreach ($tips as $tip)<li>{{ $tip }}</li>@endforeach
            </ul>
        </div>
    </x-ui.card>

    <x-ui.slide-over name="client-form" :title="$editingId ? 'Edit client' : 'Add a client'">
        <form wire:submit="save" id="client-form" class="space-y-4">
            <x-ui.input wire:model="name" label="Client name" />
            <x-ui.input wire:model="contactEmail" type="email" label="Contact email" optional />
            <x-ui.select wire:model.live="package" label="Package" :options="collect($packages)->pluck('name', 'key')->all()" />
            <x-ui.input wire:model="monthlyFee" type="number" step="0.01" min="0" label="Monthly fee ($)" />
            <x-ui.select wire:model="contractStatus" label="Contract" :options="$statuses" />
            @if ($publications->isNotEmpty())
                <x-ui.field label="Their publications">
                    <div class="space-y-1">
                        @foreach ($publications as $id => $publicationName)
                            <x-ui.checkbox wire:model="newsletterIds" :value="$id" :label="$publicationName" :id="'client-pub-'.$id" />
                        @endforeach
                    </div>
                </x-ui.field>
            @endif
            <x-ui.textarea wire:model="notes" label="Notes" rows="3" optional />
        </form>
        <x-slot:footer>
            @if ($editingId)
                <x-ui.button variant="ghost" wire:click="delete({{ $editingId }})" wire:confirm="Remove this client?" class="mr-auto">Remove</x-ui.button>
            @endif
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button type="submit" form="client-form" area="earn" loading="save">Save client</x-ui.button>
        </x-slot:footer>
    </x-ui.slide-over>

    <x-ui.modal name="contract" title="Contract" max-width="2xl">
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <x-ui.input wire:model.live.debounce.400ms="agencyName" label="Your business name" />
            <x-ui.input wire:model.live="startDate" type="date" label="Start date" />
            <x-ui.input wire:model.live="noticeDays" type="number" min="0" label="Notice (days)" />
        </div>
        @if ($contract)
            <pre class="max-h-96 overflow-y-auto rounded-control bg-subtle p-4 font-sans text-sm whitespace-pre-wrap text-ink">{{ $contract }}</pre>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Close</x-ui.button>
            @if ($contract)
                <x-ui.button area="earn" icon="clipboard" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText(@js($contract)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copied' : 'Copy contract'">Copy contract</span>
                </x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
