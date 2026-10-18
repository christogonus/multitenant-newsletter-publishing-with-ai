<div class="space-y-6">
    <x-ui.page-header title="Ready-made kits" description="Install a complete newsletter: name, voice, a month of topics and first issues already written." icon="stack" area="create">
        <x-slot:actions>
            <x-ui.badge tone="create">{{ $limit === null ? 'Unlimited publications' : $used.' of '.$limit.' publications used' }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search kits" aria-label="Search kits" />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($kits as $kit)
            <x-ui.card wire:key="kit-{{ $kit->id }}" class="flex flex-col">
                <div class="flex items-start gap-3">
                    <x-ui.area-chip area="create" icon="newspaper" />
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-ink">{{ $kit->name }}</h3>
                        <p class="text-sm text-muted">{{ $kit->tagline }}</p>
                    </div>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Topic</dt><dd class="text-ink">{{ $kit->niche }}</dd></div>
                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Readers</dt><dd class="text-ink">{{ $kit->audience }}</dd></div>
                    <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Includes</dt><dd class="text-ink">{{ count($kit->topics) }} topics, {{ count($kit->seed_issues) }} ready issues</dd></div>
                </dl>
                <div class="mt-5 flex justify-end">
                    @if (in_array($kit->key, $installed, true))
                        <x-ui.badge tone="success" icon="check">Installed</x-ui.badge>
                    @else
                        <x-ui.button area="create" wire:click="install({{ $kit->id }})" loading="install({{ $kit->id }})" icon="download">Install</x-ui.button>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state class="md:col-span-2 xl:col-span-3" icon="stack" area="create" title="No kits match" description="Try another word." />
        @endforelse
    </div>

    <x-ui.card title="Your commercial rights" description="What you may do with kit content.">
        <ul class="space-y-2 text-sm text-ink">
            <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />Publish kit issues under your own name and brand.</li>
            <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />Edit, rewrite and add to anything in a kit.</li>
            <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />Earn money from newsletters built on a kit, including sponsors and paid subscriptions.</li>
            <li class="flex gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />Run a kit newsletter for a client.</li>
            <li class="flex gap-2"><x-ui.icon name="x-mark" size="4" class="mt-0.5 text-danger" />Resell or give away the kits themselves as a product.</li>
        </ul>
    </x-ui.card>
</div>
