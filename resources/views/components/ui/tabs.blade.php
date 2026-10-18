@props(['tabs' => [], 'default' => null, 'area' => null])

{{-- Panels inside the slot use <x-ui.tab-panel name="key">; the active key lives in Alpine. --}}
<div x-data="{ tab: @js($default ?? array_key_first($tabs)) }" {{ $attributes }}>
    <div role="tablist" class="flex gap-1 overflow-x-auto rounded-control bg-subtle p-1">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                x-on:click="tab = @js($key)"
                x-bind:aria-selected="(tab === @js($key)).toString()"
                x-bind:class="tab === @js($key) ? 'bg-card text-ink shadow-card' : 'text-muted hover:text-ink'"
                class="min-h-11 flex-1 rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap transition sm:min-h-9"
            >{{ $label }}</button>
        @endforeach
    </div>
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
