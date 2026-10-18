@props(['value', 'label' => null, 'size' => 'md'])

<div {{ $attributes->class('w-full') }} x-data="{ copied: false, copy() { navigator.clipboard.writeText(@js($value)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
    @if ($label)
        <p class="mb-1.5 text-sm font-medium text-ink">{{ $label }}</p>
    @endif
    <div class="flex items-stretch gap-2">
        <input
            type="text"
            readonly
            value="{{ $value }}"
            x-on:focus="$el.select()"
            aria-label="{{ $label ?? 'Link' }}"
            @class([
                'min-w-0 flex-1 rounded-control border border-line bg-subtle px-3 font-mono text-ink',
                'min-h-14 text-base' => $size === 'lg',
                'min-h-11 text-sm' => $size !== 'lg',
            ])
        />
        <button
            type="button"
            x-on:click="copy()"
            @class([
                'inline-flex shrink-0 items-center gap-2 rounded-control px-4 font-semibold shadow-card',
                \App\Support\Ui::solid(null),
                'min-h-14' => $size === 'lg',
                'min-h-11 text-sm' => $size !== 'lg',
            ])
        >
            <x-ui.icon name="clipboard" size="4" x-show="! copied" />
            <x-ui.icon name="check" size="4" x-show="copied" x-cloak />
            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
        </button>
    </div>
</div>
