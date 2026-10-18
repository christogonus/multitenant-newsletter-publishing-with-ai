<div class="space-y-6">
    <x-ui.page-header title="Downloads" description="The bonuses you added at checkout." icon="download" />

    @forelse ($bumps as $bump)
        <x-ui.card wire:key="download-{{ $bump->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-ink">{{ $bump->name }}</h2>
                    @if ($bump->description)
                        <p class="mt-1 text-sm text-muted">{{ $bump->description }}</p>
                    @endif
                </div>
                @if ($bump->files->count() > 1)
                    <x-ui.button variant="secondary" size="sm" icon="download" :href="route('downloads.zip', $bump)">Download all</x-ui.button>
                @endif
            </div>

            @php
                [$images, $documents] = $bump->files->partition->isImage();
            @endphp

            @if ($bump->unlocks_product)
                <p class="mt-4 text-sm text-ink">Also included: <span class="font-semibold">{{ $bump->unlocks_product->label() }}</span>, unlocked in your menu.</p>
            @endif

            @if ($documents->isNotEmpty())
                <ul class="mt-4 divide-y divide-line rounded-control border border-line">
                    @foreach ($documents as $file)
                        <li class="flex items-center gap-3 px-3 py-2 text-sm">
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded bg-subtle text-muted"><x-ui.icon name="document-text" /></span>
                            <span class="min-w-0 flex-1 truncate text-ink">{{ $file->name }}</span>
                            <span class="hidden text-xs text-muted sm:inline">{{ $file->humanSize() }}</span>
                            <x-ui.button variant="ghost" size="sm" icon="download" :href="route('downloads.file', $file)">Download</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($images->isNotEmpty())
                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($images as $file)
                        <li class="overflow-hidden rounded-control border border-line">
                            <a href="{{ route('downloads.file', $file) }}" class="block" title="Download {{ $file->name }}">
                                <img src="{{ route('downloads.file', ['file' => $file, 'inline' => 1]) }}" alt="{{ $file->name }}" loading="lazy" class="aspect-square w-full bg-subtle object-cover" />
                                <span class="block truncate px-2 py-1.5 text-xs text-muted">{{ $file->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($bump->files->isEmpty() && ! $bump->unlocks_product)
                <p class="mt-4 text-sm text-muted">Your files are being prepared. Check back soon, or contact support.</p>
            @endif
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state icon="download" title="No downloads yet" description="Bonuses you add at checkout appear here." />
        </x-ui.card>
    @endforelse
</div>
