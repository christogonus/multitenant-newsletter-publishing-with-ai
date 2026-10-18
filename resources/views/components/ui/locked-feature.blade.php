@props(['product', 'compact' => false])

@php
    /** @var \App\Enums\Product $product */
    $area = $product->area();
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-hero border-2 bg-card shadow-card', \App\Support\Ui::border($area)]) }}>
    <div @class(['flex flex-col gap-5 sm:flex-row sm:items-center', 'p-5' => $compact, 'p-6 sm:p-8' => ! $compact])>
        <div class="flex items-center gap-4">
            <x-ui.area-chip :area="$area" icon="lock-closed" size="lg" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-bold text-ink">{{ $product->label() }}</h2>
                <x-ui.badge :tone="$area->value" icon="lock-closed">Upgrade</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-muted">{{ $product->benefit() }}</p>
            @if ($slot->isNotEmpty())
                <div class="mt-3 text-sm text-ink">{{ $slot }}</div>
            @endif
        </div>
        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
            <span class="text-2xl font-bold text-ink">{{ $product->price() }} <span class="text-sm font-medium text-muted">one time</span></span>
            <x-ui.button :href="$product->checkoutUrl()" :area="$area" size="lg" icon-right="arrow-right" target="_blank" rel="noopener">
                Upgrade now
            </x-ui.button>
        </div>
    </div>
</div>
