@props([
    'variant' => 'primary',
    'size' => 'md',
    'area' => null,
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'loading' => null,
    'type' => 'button',
])

@php
    $sizes = [
        'sm' => 'min-h-11 sm:min-h-9 px-3 text-sm',
        'md' => 'min-h-11 px-4 text-sm',
        'lg' => 'min-h-12 px-6 text-base',
    ];

    $variants = [
        'primary' => \App\Support\Ui::solid($area).' shadow-card',
        'secondary' => 'bg-card text-ink border border-line hover:bg-subtle shadow-card',
        'ghost' => 'text-ink hover:bg-subtle',
        'danger' => \App\Support\Ui::solid('danger'),
    ];

    $classes = 'relative inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '
        .($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="4" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-ui.icon :name="$iconRight" size="4" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif>
        @if ($loading)
            <svg wire:loading wire:target="{{ $loading }}" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
        @endif
        @if ($icon)<x-ui.icon :name="$icon" size="4" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-ui.icon :name="$iconRight" size="4" />@endif
    </button>
@endif
