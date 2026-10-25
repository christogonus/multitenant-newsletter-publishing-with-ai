<div class="space-y-6">
    <x-ui.page-header title="Email services" description="Send your newsletters to inboxes through the service you already use." icon="envelope" area="publish">
        <x-slot:actions>
            <x-ui.badge tone="publish">{{ $limit === null ? 'Unlimited connections' : $used.' of '.$limit.' connected' }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert title="Your email service sends the emails">
        New readers are copied to your service, which asks them to confirm before they get anything. Turn on confirmation (often called "double opt-in") in your service's settings. Newsletters arrive there as drafts so you can check them before sending.
    </x-ui.alert>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($platforms as $item)
            @php
                /** @var \App\Enums\Platform $platform */
                $platform = $item['platform'];
                $connection = $item['connection'];
                $isEditing = $editing === $platform->value;
            @endphp
            <x-ui.card wire:key="platform-{{ $platform->value }}" :title="$platform->label()">
                <x-slot:actions>
                    @if ($connection)
                        <x-ui.pill :tone="$connection->status === \App\Enums\ConnectionStatus::Connected ? 'success' : 'danger'">
                            {{ $connection->status === \App\Enums\ConnectionStatus::Connected ? 'Connected' : 'Needs attention' }}
                        </x-ui.pill>
                    @endif
                </x-slot:actions>

                <ol class="list-inside list-decimal space-y-1 text-sm text-muted">
                    @foreach ($item['driver']->steps() as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>

                @if ($connection?->last_error)
                    <x-ui.alert tone="danger" class="mt-4">{{ $connection->last_error }}</x-ui.alert>
                @endif

                @if ($connection && ! $isEditing)
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">Readers copied</dt><dd class="text-ink">{{ $connection->last_synced_at?->diffForHumans() ?? 'Not yet' }}</dd></div>
                        <div><dt class="text-muted">Last newsletter sent</dt><dd class="text-ink">{{ $connection->last_pushed_at?->diffForHumans() ?? 'Not yet' }}</dd></div>
                    </dl>
                @endif

                @if ($isEditing)
                    <form wire:submit="connect" class="mt-5 space-y-4">
                        @foreach ($item['driver']->fields() as $field)
                            <x-ui.input
                                wire:model="credentials.{{ $field['key'] }}"
                                :label="$field['label']"
                                :hint="$field['hint']"
                                :type="$field['secret'] ? 'password' : 'text'"
                                :optional="! $field['required']"
                                :placeholder="$field['secret'] && $connection ? 'Saved. Leave empty to keep it.' : ''"
                                autocomplete="off"
                            />
                        @endforeach
                        @error('connection')
                            <x-ui.alert tone="danger">{{ $message }}</x-ui.alert>
                        @enderror
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-ui.button variant="ghost" wire:click="cancel">Cancel</x-ui.button>
                            <x-ui.button type="submit" area="publish" loading="connect">{{ $platform->needsCredentials() ? 'Test connection and save' : 'Turn on' }}</x-ui.button>
                        </div>
                    </form>
                @endif

                <x-slot:footer>
                    @if ($platform === \App\Enums\Platform::HtmlExport)
                        <x-ui.button variant="secondary" :href="route('issues.index')" icon="download">Pick a newsletter to download</x-ui.button>
                    @elseif ($connection && ! $isEditing)
                        <x-ui.button variant="ghost" size="sm" wire:click="disconnect({{ $connection->id }})" wire:confirm="Disconnect {{ $platform->label() }}?">Disconnect</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="edit('{{ $platform->value }}')">Change details</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="test({{ $connection->id }})" loading="test">Test connection</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="syncNow({{ $connection->id }})">Copy readers now</x-ui.button>
                        <x-ui.button area="publish" size="sm" wire:click="sendLatest({{ $connection->id }})" icon="envelope">Send latest newsletter</x-ui.button>
                    @elseif (! $isEditing && $canAdd)
                        <x-ui.button area="publish" wire:click="edit('{{ $platform->value }}')" icon="link">Connect</x-ui.button>
                    @endif
                </x-slot:footer>
            </x-ui.card>
        @endforeach
    </div>

    @if ($recentDeliveries->isNotEmpty())
        <x-ui.card title="Recent sends">
            <ul class="divide-y divide-line text-sm">
                @foreach ($recentDeliveries as $delivery)
                    <li class="flex flex-wrap items-center gap-3 py-3">
                        <span class="flex-1 font-medium text-ink">{{ $delivery->issue?->title }}</span>
                        <span class="text-muted">{{ $delivery->platformConnection?->platform->label() }}</span>
                        <x-ui.pill :tone="match ($delivery->status) { 'sent' => 'success', 'failed' => 'danger', default => 'neutral' }">{{ ucfirst($delivery->status) }}</x-ui.pill>
                        @if ($delivery->error)<p class="w-full text-danger">{{ $delivery->error }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
