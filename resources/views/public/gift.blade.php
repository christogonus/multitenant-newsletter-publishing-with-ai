<x-public.layout :newsletter="$newsletter" :theme="$theme" :title="$leadPage->magnet_title">
    <article class="card">
        <p class="small muted" style="margin: 0 0 8px;">Your free gift from {{ $newsletter->name }}</p>
        <h1 style="font-size: 30px;">{{ $leadPage->magnet_title }}</h1>
        <p class="small muted">Bookmark this page. It is yours to keep.</p>
        <x-public.blocks :blocks="$leadPage->magnet_blocks ?? []" />
    </article>

    <div class="card section">
        <h2 style="font-size: 20px;">Know someone who would like this?</h2>
        <input class="field" type="text" readonly value="{{ $shareUrl }}" aria-label="Your share link" onfocus="this.select()">
        <x-public.share :url="$shareUrl" :text="$leadPage->magnet_title" />
    </div>
</x-public.layout>
