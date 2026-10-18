@props(['lines' => 3])

<div {{ $attributes->class('animate-pulse space-y-3') }} aria-hidden="true">
    @for ($line = 0; $line < $lines; $line++)
        <div @class(['h-4 rounded bg-subtle', 'w-2/3' => $line === $lines - 1, 'w-full' => $line !== $lines - 1])></div>
    @endfor
</div>
