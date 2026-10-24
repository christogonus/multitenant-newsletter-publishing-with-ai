<x-public.layout :newsletter="$newsletter" :theme="$theme" title="Details deleted">
    <main class="card">
        <h1 style="font-size: 28px;">Your details are gone</h1>
        <p>We erased your email from {{ $newsletter->name }}.</p>
    </main>
</x-public.layout>
