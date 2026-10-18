@props(['href' => null, 'icon' => null, 'danger' => false, 'type' => 'button'])

@php
    $classes = 'flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm transition hover:bg-subtle sm:min-h-10 '.($danger ? 'text-danger' : 'text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="4" class="text-muted" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="4" class="text-muted" />@endif
        {{ $slot }}
    </button>
@endif
