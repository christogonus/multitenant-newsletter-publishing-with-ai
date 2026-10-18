<div class="space-y-6">
    <x-ui.page-header title="Buyers" description="Accounts, purchases and refunds." icon="shield">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.spend')">AI spend</x-ui.button>
            <x-ui.button variant="ghost" href="/pulse" icon-right="external">Pulse</x-ui.button>
            <x-ui.button variant="ghost" href="/horizon" icon-right="external">Queues</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search name or email" aria-label="Search buyers" class="flex-1" />
        <x-ui.checkbox wire:model.live="revokedOnly" label="Only refunded or charged back" />
    </div>

    <x-ui.table :headers="['Buyer', 'Publications', 'Joined', '']" :empty="$users->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="users" title="No buyers found" /></x-slot:emptyState>
        @foreach ($users as $user)
            <tr wire:key="admin-user-{{ $user->id }}">
                <td class="px-4 py-3"><p class="font-medium text-ink">{{ $user->name }} @if ($user->is_admin)<x-ui.badge>Admin</x-ui.badge>@endif</p><p class="text-xs text-muted">{{ $user->email }}</p></td>
                <td class="px-4 py-3 text-muted tabular-nums">{{ $user->newsletters_count }}</td>
                <td class="px-4 py-3 text-muted">{{ $user->created_at->toFormattedDateString() }}</td>
                <td class="px-4 py-3 text-right"><x-ui.button variant="secondary" size="sm" wire:click="select({{ $user->id }})">Open</x-ui.button></td>
            </tr>
        @endforeach
        @if ($users->hasPages())
            <x-slot:footer>{{ $users->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.slide-over name="buyer" :title="$selected?->email ?? 'Buyer'">
        @if ($selected)
            <div class="space-y-6">
                <div>
                    <p class="text-sm font-semibold text-ink">Owns now</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (\App\Enums\Product::cases() as $product)
                            @if ($selected->owns($product))<x-ui.badge :tone="$product->area()->value">{{ $product->label() }}</x-ui.badge>@endif
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold text-ink">Ledger</p>
                    <ul class="mt-2 divide-y divide-line text-sm">
                        @foreach ($selected->purchases as $purchase)
                            <li class="flex flex-wrap items-center gap-2 py-2">
                                <x-ui.pill :tone="$purchase->action->revokes() ? 'danger' : 'success'">{{ ucfirst($purchase->action->value) }}</x-ui.pill>
                                <span class="flex-1 text-ink">{{ $purchase->product_key->label() }}</span>
                                <span class="text-xs text-muted">{{ $purchase->source->value }} · {{ $purchase->created_at->toDateString() }}</span>
                                @if (! $purchase->action->revokes() && ! in_array($purchase->wp_txn_id, $reversedTxns, true))
                                    <x-ui.button variant="ghost" size="sm" wire:click="revoke({{ $purchase->id }})" wire:confirm="Revoke this purchase? The feature switches off.">Revoke</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <form wire:submit="grant" class="flex items-end gap-2">
                    <x-ui.select wire:model="grantProduct" label="Grant a product" :options="$products" class="flex-1" />
                    <x-ui.button type="submit" loading="grant">Grant</x-ui.button>
                </form>
            </div>
        @endif
    </x-ui.slide-over>
</div>
