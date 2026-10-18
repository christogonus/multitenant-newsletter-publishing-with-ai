@props(['tone' => 'info', 'title' => null])

@php
    $icon = match ($tone) {
        'success' => 'check-circle',
        'warning' => 'warning',
        'danger' => 'warning',
        default => 'info',
    };
    $tint = $tone === 'info' ? 'brand' : $tone;
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['flex gap-3 rounded-card p-4 text-sm', \App\Support\Ui::tint($tint)]) }}>
    <x-ui.icon :name="$icon" size="5" class="mt-px" />
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</div>
