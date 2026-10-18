<x-layouts::app :title="$product->label()">
    <div class="space-y-6">
        @if ($product === \App\Enums\Product::FrontEnd)
            <x-ui.alert tone="warning" title="Your NewsPub AI access is not active">
                This can happen after a refund. If you think this is a mistake, contact us from the Help page.
            </x-ui.alert>
        @endif

        <x-ui.locked-feature :product="$product">
            @include('partials.product-highlights', ['product' => $product])
        </x-ui.locked-feature>

        <p class="text-center text-sm text-muted">
            Already bought it? It can take a minute to unlock. <a href="{{ url()->current() }}" class="font-medium text-brand underline">Refresh this page</a>
            or visit <a href="{{ route('support') }}" class="font-medium text-brand underline">Help</a>.
        </p>
    </div>
</x-layouts::app>
