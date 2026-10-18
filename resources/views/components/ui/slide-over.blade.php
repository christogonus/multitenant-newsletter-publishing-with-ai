@props(['name', 'title' => null])

<div
    x-data="{ open: false }"
    x-on:open-slide-over.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name) || $event.detail?.name === @js($name)) open = true"
    x-on:close-slide-over.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name) || $event.detail?.name === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>
    <div
        x-show="open"
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-trap.noscroll="open"
        {{ $attributes->class('fixed inset-y-0 right-0 flex w-full max-w-md flex-col bg-card shadow-raised') }}
    >
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
            <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" class="-mr-2 inline-flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink" aria-label="Close">
                <x-ui.icon name="x-mark" />
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
