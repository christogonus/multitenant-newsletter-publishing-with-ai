@props(['icon' => 'sparkles', 'title', 'description' => null, 'area' => null])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <x-ui.area-chip :area="$area" :icon="$icon" size="lg" />
    <h3 class="mt-4 text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
