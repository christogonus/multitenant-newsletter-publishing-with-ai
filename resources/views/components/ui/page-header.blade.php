@props(['title', 'description' => null, 'area' => null, 'icon' => null, 'back' => null])

<div {{ $attributes->class(['relative overflow-hidden rounded-hero border border-line bg-card px-5 py-6 shadow-card sm:px-8']) }}>
    <div @class(['absolute inset-x-0 top-0 h-1', \App\Support\Ui::bar($area)])></div>
    @if ($back)
        <a href="{{ $back }}" class="mb-3 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-muted hover:text-ink sm:min-h-0">
            <x-ui.icon name="arrow-left" size="4" /> Back
        </a>
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            @if ($icon)
                <x-ui.area-chip :area="$area" :icon="$icon" size="lg" />
            @endif
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $title }}</h1>
                @if ($description)<p class="mt-1 text-sm text-muted">{{ $description }}</p>@endif
            </div>
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
