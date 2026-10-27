<div class="space-y-6">
    <x-ui.page-header title="Emails" description="Send an announcement to your customers." icon="envelope">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" href="/horizon" icon-right="external">Queues</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-admin.trend-radar-notice :with-button="false" />

    <form wire:submit="send" class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <x-ui.card title="Message">
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input wire:model="fromName" label="Sender name" />
                    <x-ui.input wire:model="fromEmail" type="email" label="Sender email" hint="Use an address on your sending domain, or inboxes may reject it. Replies go here." />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-muted">Start from a sample:</span>
                    @foreach ($samples as $key => $label)
                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="useSample('{{ $key }}')" wire:confirm="Replace the subject, message and button with the {{ $label }} sample?">{{ $label }}</x-ui.button>
                    @endforeach
                </div>

                <x-ui.input wire:model="subject" label="Subject" placeholder="For example: New this week in {{ config('app.name') }}" />

                <div
                    x-data="{
                        wrap(before, after, placeholder) {
                            const field = this.$refs.body;
                            const start = field.selectionStart, end = field.selectionEnd;
                            const selected = field.value.slice(start, end) || placeholder;
                            field.setRangeText(before + selected + after, start, end, 'end');
                            field.setSelectionRange(start + before.length, start + before.length + selected.length);
                            this.changed();
                        },
                        prefixLines(prefix) {
                            const field = this.$refs.body;
                            const start = field.value.lastIndexOf('\n', field.selectionStart - 1) + 1;
                            const end = field.selectionEnd;
                            const lines = field.value.slice(start, end).split('\n');
                            const text = lines.map((line, index) => (prefix === '1. ' ? (index + 1) + '. ' : prefix) + line).join('\n');
                            field.setRangeText(text, start, end, 'select');
                            this.changed();
                        },
                        link() {
                            const url = window.prompt('Link address', 'https://');
                            if (url) { this.wrap('[', '](' + url + ')', 'link text'); }
                        },
                        insert(text) {
                            const field = this.$refs.body;
                            field.setRangeText(text, field.selectionStart, field.selectionEnd, 'end');
                            this.changed();
                        },
                        changed() {
                            this.$refs.body.focus();
                            this.$refs.body.dispatchEvent(new Event('input', { bubbles: true }));
                        },
                    }"
                >
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-ink">Message</p>
                        <div class="flex gap-1 rounded-control bg-subtle p-1" role="tablist">
                            <button type="button" role="tab" wire:click="$set('previewing', false)" @class(['min-h-9 rounded-[6px] px-3 text-sm font-semibold', 'bg-card text-ink shadow-card' => ! $previewing, 'text-muted hover:text-ink' => $previewing]) aria-selected="{{ $previewing ? 'false' : 'true' }}">Write</button>
                            <button type="button" role="tab" wire:click="$set('previewing', true)" @class(['min-h-9 rounded-[6px] px-3 text-sm font-semibold', 'bg-card text-ink shadow-card' => $previewing, 'text-muted hover:text-ink' => ! $previewing]) aria-selected="{{ $previewing ? 'true' : 'false' }}">Preview</button>
                        </div>
                    </div>

                    <div @class(['hidden' => $previewing])>
                        <div class="flex flex-wrap gap-1 rounded-t-control border border-b-0 border-line bg-subtle p-1" role="toolbar" aria-label="Formatting">
                            <button type="button" x-on:click="wrap('**', '**', 'bold text')" class="min-h-9 min-w-9 rounded-[6px] px-2 text-sm font-bold text-ink hover:bg-card" title="Bold">B</button>
                            <button type="button" x-on:click="wrap('*', '*', 'italic text')" class="min-h-9 min-w-9 rounded-[6px] px-2 text-sm italic text-ink hover:bg-card" title="Italic">I</button>
                            <button type="button" x-on:click="prefixLines('## ')" class="min-h-9 rounded-[6px] px-2 text-sm font-semibold text-ink hover:bg-card" title="Heading">Heading</button>
                            <button type="button" x-on:click="prefixLines('- ')" class="min-h-9 rounded-[6px] px-2 text-sm text-ink hover:bg-card" title="Bulleted list">• List</button>
                            <button type="button" x-on:click="prefixLines('1. ')" class="min-h-9 rounded-[6px] px-2 text-sm text-ink hover:bg-card" title="Numbered list">1. List</button>
                            <button type="button" x-on:click="link()" class="inline-flex min-h-9 items-center gap-1 rounded-[6px] px-2 text-sm text-ink hover:bg-card" title="Link"><x-ui.icon name="link" size="4" />Link</button>
                            <button type="button" x-on:click="insert('{first_name}')" class="min-h-9 rounded-[6px] px-2 text-sm text-ink hover:bg-card" title="Insert the customer's first name">+ First name</button>
                        </div>
                        <textarea
                            x-ref="body"
                            wire:model="body"
                            rows="16"
                            aria-label="Message"
                            @if ($errors->has('body')) aria-invalid="true" @endif
                            @class([
                                'block w-full rounded-b-control border bg-card px-3 py-2.5 font-mono text-sm text-ink shadow-sm focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none',
                                'border-danger' => $errors->has('body'),
                                'border-line' => ! $errors->has('body'),
                            ])
                        ></textarea>
                        <p class="mt-1.5 text-sm text-muted">Select text and press a button to format it. {first_name} and {name} become each customer's name, in the subject too.</p>
                        @error('body')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                    </div>

                    @if ($previewing)
                        <div>
                            <iframe srcdoc="{{ $previewHtml }}" sandbox title="Email preview" class="h-[36rem] w-full rounded-control border border-line bg-white"></iframe>
                            <p class="mt-1.5 text-sm text-muted">This is how it looks for you. Each customer sees their own name.</p>
                        </div>
                    @endif
                </div>
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
