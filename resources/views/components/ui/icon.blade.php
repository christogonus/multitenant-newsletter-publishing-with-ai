@props(['name', 'size' => 5])

@php
    $sizeClass = match ((int) $size) {
        3 => 'size-3',
        4 => 'size-4',
        6 => 'size-6',
        8 => 'size-8',
        10 => 'size-10',
        default => 'size-5',
    };
@endphp

<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" focusable="false" {{ $attributes->class([$sizeClass, 'shrink-0']) }}>{!! \App\Support\Ui::icon($name) !!}</svg>
