<div class="space-y-6">
    <x-ui.page-header title="Buyers" description="Accounts, purchases and refunds." icon="shield">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="openDemoForm">Create demo account</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.broadcasts')">Emails</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.spend')">AI spend</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.bumps')">Order bumps</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.content')">Content</x-ui.button>
            <x-ui.button variant="ghost" href="/pulse" icon-right="external">Pulse</x-ui.button>
            <x-ui.button variant="ghost" href="/horizon" icon-right="external">Queues</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-admin.trend-radar-notice />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search name or email" aria-label="Search buyers" class="flex-1" />
        <x-ui.checkbox wire:model.live="revokedOnly" label="Only refunded or charged back" />
        <x-ui.checkbox wire:model.live="demoOnly" label="Only demo accounts" />
        <x-ui.checkbox wire:model.live="suspendedOnly" label="Only suspended" />
    </div>

    <x-ui.table :headers="['Buyer', 'Publications', 'Joined', '']" :empty="$users->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="users" title="No buyers found" /></x-slot:emptyState>
        @foreach ($users as $user)
            <tr wire:key="admin-user-{{ $user->id }}">
                <td class="px-4 py-3"><p class="font-medium text-ink">{{ $user->name }} @if ($user->is_admin)<x-ui.badge>Admin</x-ui.badge>@endif @if ($user->is_demo)<x-ui.badge tone="warning">Demo</x-ui.badge>@endif @if ($user->isSuspended())<x-ui.badge tone="danger">Suspended</x-ui.badge>@endif</p><p class="text-xs text-muted">{{ $user->email }}</p></td>
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
                                <span class="flex-1 text-ink">{{ $purchase->itemLabel() }}</span>
                                <span class="text-xs text-muted">{{ $purchase->source->value }} · {{ $purchase->created_at->toDateString() }}@if ($purchase->buyer_email && $purchase->buyer_email !== $selected->email) · paid as {{ $purchase->buyer_email }}@endif</span>
                                @if (! $purchase->action->revokes() && ! in_array($purchase->transaction_id.'|'.$purchase->item_key, $reversedTxns, true))
                                    <x-ui.button variant="ghost" size="sm" icon="eye" :href="route('admin.emails.upgrade', $purchase)" target="_blank">Preview</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" icon="envelope" wire:click="resendUpgrade({{ $purchase->id }})" loading="resendUpgrade({{ $purchase->id }})">Send upgrade email</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="revoke({{ $purchase->id }})" wire:confirm="Revoke this purchase? The feature switches off.">Revoke</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 rounded-card border border-line p-3">
                    <div>
                        <p class="text-sm font-semibold text-ink">Welcome email</p>
                        <p class="text-xs text-muted">{{ $selected->uses_default_password ? 'Still on the starting password. The email shows it again.' : ($selected->email_verified_at ? 'Account set up. A new email works like a password reset.' : 'Password not set yet.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="ghost" size="sm" icon="eye" :href="route('admin.emails.welcome', $selected)" target="_blank">Preview</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" icon="envelope" wire:click="resendWelcome" loading="resendWelcome" wire:confirm="Send {{ $selected->email }} a new welcome email with a set-password link?">Resend welcome email</x-ui.button>
                    </div>
                </div>

                <form wire:submit="grant" class="flex items-end gap-2">
                    <x-ui.select wire:model="grantProduct" label="Grant a product or order bump" :options="$grantOptions" class="flex-1" />
                    <x-ui.button type="submit" loading="grant">Grant</x-ui.button>
                </form>

                @if (! $selected->is_admin && ! $selected->is(auth()->user()))
                    <div class="space-y-3 border-t border-line pt-4">
                        <p class="text-sm font-semibold text-ink">Account status</p>
                        @if ($selected->isSuspended())
                            <x-ui.alert tone="danger" title="Suspended {{ $selected->suspended_at->diffForHumans() }}">
                                Their dashboard is closed and their publications show “coming soon”.
                                @if ($selected->suspension_reason)<br>Note: {{ $selected->suspension_reason }}@endif
                            </x-ui.alert>
                            <x-ui.button variant="secondary" icon="check" wire:click="unsuspend" loading="unsuspend">Lift suspension</x-ui.button>
                        @else
                            <form wire:submit="suspend" class="space-y-3">
                                <x-ui.input wire:model="suspensionReason" label="Private note" hint="Only admins see this. The owner is told to contact support." placeholder="e.g. Chargeback under review" optional />
                                <x-ui.button type="submit" variant="secondary" icon="x-mark" loading="suspend" wire:confirm="Suspend {{ $selected->email }}? Their dashboard closes and their publications show “coming soon” until you lift it.">Suspend account</x-ui.button>
                            </form>
                        @endif
                    </div>

                    <div class="space-y-3 rounded-card border border-danger/40 p-4">
                        <p class="text-sm font-semibold text-danger">Delete account</p>
                        <p class="text-sm text-muted">Erases this account with its {{ $selected->newsletters_count }} {{ \Illuminate\Support\Str::plural('publication', $selected->newsletters_count) }}, every newsletter, reader and upload, and its purchase history. This cannot be undone.</p>
                        <form wire:submit="deleteUser" class="space-y-3">
                            <x-ui.input wire:model="deleteConfirmation" :label="'Type '.$selected->email.' to confirm'" autocomplete="off" />
                            <x-ui.button type="submit" variant="danger" icon="trash" loading="deleteUser">Delete account and everything in it</x-ui.button>
                        </form>
                    </div>
                @endif

                @if ($selected->is_demo && ! $selected->is_admin)
                    <div class="space-y-3 rounded-card border border-line p-3">
                        <div>
                            <p class="text-sm font-semibold text-ink">Demo password</p>
                            <p class="text-xs text-muted">People signed in to a demo account cannot change its password, email or two-step sign in. A new password also turns off two-step sign in and signs everyone out.</p>
                        </div>
                        @if ($demoNewPassword)
                            <x-ui.alert tone="success">Copy the new password now. It is not shown again.</x-ui.alert>
                            <x-ui.copy-field label="New password" :value="$demoNewPassword" />
                        @endif
                        <x-ui.button variant="secondary" size="sm" icon="lock-closed" wire:click="resetDemoPassword" loading="resetDemoPassword" wire:confirm="Give {{ $selected->email }} a new password? Everyone signed in to it is signed out.">New password</x-ui.button>
                    </div>

                    <div class="border-t border-line pt-4">
                        <x-ui.button variant="danger" icon="trash" wire:click="deleteDemo({{ $selected->id }})" wire:confirm="Delete this demo account and everything made in it?">Delete demo account</x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.slide-over>

    <x-ui.modal name="demo-account" title="Create demo account" max-width="md">
        @if ($demoLogin)
            <div class="space-y-4">
                <x-ui.alert tone="success" title="Demo account ready">Copy the password now. It is not shown again.</x-ui.alert>
                <x-ui.copy-field label="Email" :value="$demoLogin['email']" />
                <x-ui.copy-field label="Password" :value="$demoLogin['password']" />
                <x-ui.copy-field label="Sign in at" :value="route('login')" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="openDemoForm">Create another</x-ui.button>
                <x-ui.button x-on:click="open = false; $wire.set('demoLogin', null)">Done</x-ui.button>
            </x-slot:footer>
        @else
            <form id="demo-form" wire:submit="createDemo" class="space-y-4">
                <p class="text-sm text-muted">A ready-to-use account for testing or showing the product. It is marked Demo, gets no welcome email, and can be deleted with everything in it.</p>
                <x-ui.input wire:model="demoName" label="Name" />
                <x-ui.input wire:model="demoEmail" type="email" label="Email" hint="Any address you control. No email is sent to it." />
                <fieldset>
                    <legend class="text-sm font-medium text-ink">Products</legend>
                    <div class="mt-1 grid gap-x-4 sm:grid-cols-2">
                        @foreach ($products as $value => $label)
                            <x-ui.checkbox wire:model="demoProducts" :value="$value" :id="'demo-product-'.$value" :label="$label" :disabled="$value === 'front_end'" />
                        @endforeach
                    </div>
                    @error('demoProducts')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                </fieldset>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button type="submit" form="demo-form" loading="createDemo">Create account</x-ui.button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
