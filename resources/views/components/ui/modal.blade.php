@props(['name', 'title' => null, 'maxWidth' => 'lg'])

@php
    $width = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

{{-- Open with $dispatch('open-modal', 'name') from Alpine or $this->dispatch('open-modal', 'name') from Livewire. --}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name) || $event.detail?.name === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name) || $event.detail?.name === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition
        x-trap.noscroll="open"
        {{ $attributes->class(['relative w-full rounded-t-hero bg-card shadow-raised sm:rounded-hero', $width]) }}
    >
        @if ($title)
            <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" class="-mr-2 inline-flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink" aria-label="Close">
                    <x-ui.icon name="x-mark" />
                </button>
            </div>
        @endif
        <div class="max-h-[75vh] overflow-y-auto p-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
