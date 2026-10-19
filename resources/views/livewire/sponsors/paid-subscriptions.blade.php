<div class="space-y-6">
    <x-ui.card title="Paid subscriptions" description="Lock chosen issues so only paying readers can finish them. Readers pay you directly through your own Stripe or PayPal.">
        <x-slot:actions>
            <x-ui.pill :tone="$this->newsletter->paid_enabled ? 'success' : 'neutral'">{{ $this->newsletter->paid_enabled ? 'On' : 'Off' }}</x-ui.pill>
        </x-slot:actions>

        <form wire:submit="saveSettings" class="space-y-4">
            <x-ui.checkbox wire:model="paidEnabled" label="Turn on paid subscriptions" hint="Issues you mark “Paying readers only” in the editor show their opening, then ask readers to sign in or subscribe." />

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input wire:model="monthlyPrice" type="number" step="0.01" min="1" label="Monthly price" placeholder="5.00" optional />
                <x-ui.input wire:model="yearlyPrice" type="number" step="0.01" min="1" label="Yearly price" placeholder="50.00" optional />
                <x-ui.select wire:model="currency" label="Currency" :options="$currencies" />
            </div>

            <x-ui.textarea wire:model="perks" rows="3" label="What paying readers get" hint="Shown on locked issues and the subscribe page." placeholder="Every issue in full, plus a bonus deep-dive each Friday." optional />

            <div class="flex justify-end">
                <x-ui.button type="submit" area="earn" loading="saveSettings">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($providers as $item)
            @php
                /** @var \App\Enums\PaymentProvider $provider */
                $provider = $item['provider'];
                $account = $item['account'];
                $isEditing = $editing === $provider->value;
            @endphp
            <x-ui.card wire:key="payment-{{ $provider->value }}" :title="$provider->label()">
                <x-slot:actions>
                    @if ($account)
                        <x-ui.pill :tone="$account->status === \App\Enums\ConnectionStatus::Connected ? 'success' : 'danger'">
                            {{ $account->status === \App\Enums\ConnectionStatus::Connected ? 'Connected' : 'Needs attention' }}
                        </x-ui.pill>
                    @endif
                </x-slot:actions>

                <ol class="list-inside list-decimal space-y-1 text-sm text-muted">
                    @foreach ($item['gateway']->steps() as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>

                @if ($account && $provider === \App\Enums\PaymentProvider::PayPal && $account->credential('mode') === 'sandbox' && ! $isEditing)
                    <x-ui.alert tone="warning" class="mt-4">Test mode: readers cannot pay with real money yet.</x-ui.alert>
                @endif

                @if ($isEditing)
                    <form wire:submit="connect" class="mt-5 space-y-4">
                        @foreach ($item['gateway']->fields() as $field)
                            <x-ui.input
                                wire:model="credentials.{{ $field['key'] }}"
                                :label="$field['label']"
                                :hint="$field['hint']"
                                :type="$field['secret'] ? 'password' : 'text'"
                                :placeholder="$field['secret'] && $account ? 'Saved. Leave empty to keep it.' : ''"
                                autocomplete="off"
                            />
                        @endforeach
                        @if ($provider === \App\Enums\PaymentProvider::PayPal)
                            <x-ui.checkbox wire:model="sandbox" label="These are Sandbox (test) keys" hint="Only for trying things out. Readers cannot pay real money in test mode." />
                        @endif
                        @error('connection')
                            <x-ui.alert tone="danger">{{ $message }}</x-ui.alert>
                        @enderror
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-ui.button variant="ghost" wire:click="cancel">Cancel</x-ui.button>
                            <x-ui.button type="submit" area="earn" loading="connect">Test and save</x-ui.button>
                        </div>
                    </form>
                @endif

                <x-slot:footer>
                    @if ($account && ! $isEditing)
                        <x-ui.button variant="ghost" size="sm" wire:click="disconnect({{ $account->id }})" wire:confirm="Disconnect {{ $provider->label() }}? New readers will not be able to pay with it.">Disconnect</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="edit('{{ $provider->value }}')">Change keys</x-ui.button>
                    @elseif (! $isEditing)
                        <x-ui.button area="earn" wire:click="edit('{{ $provider->value }}')" icon="link">Connect {{ $provider->label() }}</x-ui.button>
                    @endif
                </x-slot:footer>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card title="Pay another way" description="No Stripe or PayPal? Readers pay you by bank transfer, crypto or any other way, press “I’ve paid”, and you let them in.">
        <x-slot:actions>
            <x-ui.pill :tone="$this->newsletter->acceptsManualPayments() ? 'success' : 'neutral'">{{ $this->newsletter->acceptsManualPayments() ? 'On' : 'Off' }}</x-ui.pill>
        </x-slot:actions>

        <form wire:submit="saveManualPayments" class="space-y-4">
            <x-ui.checkbox wire:model="manualEnabled" label="Show my payment instructions to readers" hint="Readers see your prices, these instructions and your email after they confirm their own email." />
            <x-ui.textarea wire:model="manualInstructions" rows="5" label="How to pay you" hint="Bank details, a crypto wallet address and network, or a payment link. Readers see exactly what you write." placeholder="Bank: First Bank, account 0123456789, Jane Doe. Or USDT (TRC20) to T..." />
            <x-ui.input wire:model="manualContact" type="email" label="Email readers write to after paying" hint="Readers who press “I’ve paid” are emailed here and listed below for you to check. Readers with questions write here too." />
            <div class="flex justify-end">
                <x-ui.button type="submit" area="earn" loading="saveManualPayments">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($pendingClaims->isNotEmpty())
        <x-ui.card title="Waiting for activation" description="These readers say they paid. Check the money arrived, then activate them.">
            <ul class="divide-y divide-line rounded-card border border-line">
                @foreach ($pendingClaims as $claim)
                    <li wire:key="claim-{{ $claim->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-ink">{{ $claim->email }}</p>
                            <p class="text-muted">
                                @php $claimCents = $this->newsletter->paidPriceCents($claim->plan); @endphp
                                {{ $claim->plan->label() }}{{ $claimCents ? ' ('.$this->newsletter->formatPaidPrice($claimCents).')' : '' }}
                                · {{ $claim->created_at->diffForHumans() }}
                                · Reference: <span class="break-all text-ink">{{ $claim->reference ?: 'none given' }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button variant="ghost" size="sm" wire:click="declineClaim({{ $claim->id }})" wire:confirm="Decline {{ $claim->email }}? They will not get access.">Decline</x-ui.button>
                            <x-ui.button area="earn" size="sm" wire:click="activateClaim({{ $claim->id }})" icon="check">Activate</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.card title="Paying readers" :description="$payingCount.' '.\Illuminate\Support\Str::plural('reader', $payingCount).' can read locked issues right now.'">
        <x-ui.copy-field :value="$membersUrl" label="Link to your subscribe page" />

        <form wire:submit="grantAccess" class="mt-5 grid gap-4 sm:grid-cols-[1fr_200px_auto] sm:items-end">
            <x-ui.input wire:model="grantEmail" type="email" label="Give a reader access" hint="For readers who paid you another way, or a free pass." placeholder="reader@example.com" />
            <x-ui.input wire:model="grantUntil" type="date" label="Until" />
            <x-ui.button type="submit" variant="secondary" loading="grantAccess" icon="plus">Add</x-ui.button>
        </form>

        @if ($payingReaders->isNotEmpty())
            <ul class="mt-5 divide-y divide-line rounded-card border border-line">
                @foreach ($payingReaders as $reader)
                    <li wire:key="paying-{{ $reader->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <span class="font-medium text-ink">{{ $reader->email }}</span>
                        <span class="flex items-center gap-3 text-muted">
                            <x-ui.badge tone="earn">{{ $reader->paid_via?->label() }}{{ $reader->paid_plan ? ' · '.$reader->paid_plan->label() : '' }}</x-ui.badge>
                            Until {{ $reader->paid_until->toFormattedDateString() }}
                            @if ($reader->paid_via === \App\Enums\PaymentProvider::Manual)
                                <x-ui.button variant="ghost" size="sm" wire:click="revokeAccess({{ $reader->id }})" wire:confirm="Remove paid access for {{ $reader->email }}?">Remove</x-ui.button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-muted">To cancel a Stripe or PayPal subscription, cancel it in that account. The reader keeps access until the end of the time they paid for.</p>
        @endif
    </x-ui.card>
</div>
