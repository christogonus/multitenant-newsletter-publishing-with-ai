<x-layouts::app title="Upgrades">
    @php
        $user = auth()->user();
        $products = collect(\App\Enums\Product::cases())->reject(fn ($product) => $product === \App\Enums\Product::FrontEnd);
        $owned = $products->filter(fn ($product) => $user->owns($product));
        $available = $products->reject(fn ($product) => $user->owns($product));
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Upgrades" description="Add more power to your newsletter. Every upgrade is a one-time payment." icon="sparkles" />

        @forelse ($available as $product)
            <x-ui.locked-feature :product="$product" compact>
                @include('partials.product-highlights', ['product' => $product])
            </x-ui.locked-feature>
        @empty
            <x-ui.empty-state icon="check-circle" area="grow" title="You have everything" description="Every upgrade is unlocked on your account." />
        @endforelse

        @if ($owned->isNotEmpty())
            <x-ui.card title="You own">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($owned as $product)
                        <li><x-ui.badge :tone="$product->area()->value" icon="check">{{ $product->label() }}</x-ui.badge></li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</x-layouts::app>
