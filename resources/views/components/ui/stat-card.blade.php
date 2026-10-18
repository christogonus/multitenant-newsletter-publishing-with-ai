@props(['label', 'value', 'hint' => null, 'icon' => null, 'area' => null])

<div {{ $attributes->class('flex items-start gap-4 rounded-card border border-line bg-card p-5 shadow-card') }}>
    @if ($icon)
        <x-ui.area-chip :area="$area" :icon="$icon" />
    @endif
    <div class="min-w-0">
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold tracking-tight text-ink tabular-nums">{{ $value }}</p>
        @if ($hint)<p class="mt-1 text-sm text-muted">{{ $hint }}</p>@endif
    </div>
</div>
