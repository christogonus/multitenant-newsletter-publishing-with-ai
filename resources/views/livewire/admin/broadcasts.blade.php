<div class="space-y-6">
    <x-ui.page-header title="Emails" description="Send an announcement to your customers." icon="envelope">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" href="/horizon" icon-right="external">Queues</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="send" class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <x-ui.card title="Message">
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input wire:model="fromName" label="Sender name" />
                    <x-ui.input wire:model="fromEmail" type="email" label="Sender email" hint="Use an address on your sending domain, or inboxes may reject it. Replies go here." />
                </div>
                <x-ui.input wire:model="subject" label="Subject" placeholder="For example: New this week in {{ config('app.name') }}" />
                <x-ui.textarea wire:model="body" label="Message" rows="12" hint="Plain text. **Bold**, [links](https://…) and lists starting with - work. {first_name} and {name} are replaced with each customer's name." />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input wire:model="buttonLabel" label="Button text" placeholder="Open my dashboard" optional />
                    <x-ui.input wire:model="buttonUrl" type="url" label="Button link" placeholder="https://…" optional />
                </div>
            </div>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Who gets it">
                <div class="space-y-4">
                    <x-ui.segmented wire:model.live="mode" :options="['everyone' => 'Everyone', 'owners' => 'Owners of…']" />

                    @if ($mode === 'owners')
                        <fieldset>
                            <legend class="mb-1 text-sm font-medium text-ink">Owns any of</legend>
                            <div class="max-h-64 overflow-y-auto rounded-control border border-line px-3">
                                @foreach ($itemOptions as $value => $label)
                                    <x-ui.checkbox wire:model.live="items" :value="$value" :id="'item-'.$value" :label="$label" />
                                @endforeach
                            </div>
                            @error('items')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif

                    <details @if ($excludeItems) open @endif>
                        <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-brand">Leave out owners of… @if ($excludeItems)({{ count($excludeItems) }})@endif</summary>
                        <div class="max-h-64 overflow-y-auto rounded-control border border-line px-3">
                            @foreach ($itemOptions as $value => $label)
                                <x-ui.checkbox wire:model.live="excludeItems" :value="$value" :id="'exclude-'.$value" :label="$label" />
                            @endforeach
                        </div>
                    </details>

                    <x-ui.checkbox wire:model.live="includeDemo" label="Include demo accounts" />

                    <div class="rounded-card bg-subtle p-4">
                        <p class="text-3xl font-semibold text-ink tabular-nums">{{ number_format($recipientCount) }}</p>
                        <p class="text-sm text-muted">{{ \Illuminate\Support\Str::plural('person', $recipientCount) }} will get this. Suspended and unsubscribed accounts are always left out.</p>
                    </div>
                </div>
            </x-ui.card>

            <div class="flex flex-col gap-2">
                <x-ui.button type="submit" icon="envelope" loading="send" wire:confirm="Send this email to {{ number_format($recipientCount) }} {{ \Illuminate\Support\Str::plural('person', $recipientCount) }}? This cannot be undone.">Send to {{ number_format($recipientCount) }}</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="sendTest" loading="sendTest">Send a test to me</x-ui.button>
            </div>
        </div>
    </form>

    <x-ui.table :headers="['Sent', 'Subject', 'From', 'Recipients']" :empty="$history->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="envelope" title="No emails sent yet" /></x-slot:emptyState>
        @foreach ($history as $broadcast)
            <tr>
                <td class="px-4 py-3 text-muted">{{ $broadcast->sent_at?->diffForHumans() ?? 'Sending…' }}</td>
                <td class="px-4 py-3 text-ink">{{ $broadcast->subject }}</td>
                <td class="px-4 py-3 text-muted">{{ $broadcast->from_name }} &lt;{{ $broadcast->from_email }}&gt;</td>
                <td class="px-4 py-3 text-ink tabular-nums">{{ $broadcast->sent_at ? number_format($broadcast->recipients_count) : '…' }}</td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
