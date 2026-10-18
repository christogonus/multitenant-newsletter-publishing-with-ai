<div class="space-y-6">
    <x-ui.page-header title="All publications" description="Every newsletter you run, in one place." icon="squares" area="automate">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button :href="route('newsletters.create')" area="automate" icon="plus" size="lg">New publication</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Publications" :value="$publications->count()" icon="squares" area="automate" />
        <x-ui.stat-card label="Readers across all" :value="number_format($totalReaders)" icon="users" area="publish" />
    </div>

    @if ($publications->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="newspaper" area="automate" title="No publications yet" description="Create your first newsletter in about a minute.">
                <x-ui.button :href="route('newsletters.create')" area="automate" icon="plus">Start a newsletter</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($publications as $publication)
                <x-ui.card wire:key="publication-{{ $publication->id }}" @class(['ring-2 ring-automate' => $publication->id === $currentId])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-ink">{{ $publication->name }}</h3>
                            <p class="truncate text-sm text-muted">{{ $publication->niche }}</p>
                        </div>
                        @if ($publication->id === $currentId)
                            <x-ui.badge tone="automate">Open now</x-ui.badge>
                        @endif
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">Readers</dt><dd class="font-semibold text-ink tabular-nums">{{ number_format($publication->subscribers_count) }}</dd></div>
                        <div><dt class="text-muted">Live issues</dt><dd class="font-semibold text-ink tabular-nums">{{ $publication->live_issues_count }}</dd></div>
                    </dl>
                    <x-slot:footer>
                        <x-ui.button variant="ghost" size="sm" :href="$publication->publicUrl()" target="_blank" rel="noopener" icon="eye">View page</x-ui.button>
                        @if ($publication->id !== $currentId)
                            <x-ui.button size="sm" area="automate" wire:click="switchTo({{ $publication->id }})">Open</x-ui.button>
                        @endif
                    </x-slot:footer>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
