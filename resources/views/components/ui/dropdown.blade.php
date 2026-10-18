@props(['align' => 'right', 'width' => 'w-56'])

<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" {{ $attributes->class('relative') }}>
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>
    <div
        x-show="open"
        x-transition.origin.top
        x-cloak
        x-on:click="open = false"
        @class([
            'absolute z-40 mt-2 overflow-hidden rounded-card border border-line bg-card py-1 shadow-raised',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
        ])
        role="menu"
    >
        {{ $slot }}
    </div>
</div>
