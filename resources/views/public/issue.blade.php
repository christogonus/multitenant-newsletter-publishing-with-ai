<x-public.layout :newsletter="$newsletter" :theme="$theme" :title="$issue->title" :description="$issue->preheader" :canonical="$issue->publicUrl()" :report-issue-id="$issue->id">
    <article class="card">
        <p class="small muted" style="margin: 0 0 8px;">{{ $issue->published_at->toFormattedDateString() }}</p>
        <h1 style="font-size: 32px;">{{ $issue->title }}</h1>
        <x-public.blocks :blocks="$issue->blockList()" />

        <hr style="border: 0; border-top: 1px solid rgba(0,0,0,.12); margin: 32px 0 8px;">
        <p class="small muted" style="margin-bottom: 0;">Enjoyed this? Pass it on.</p>
        <x-public.share :url="$issue->publicUrl()" :text="$issue->title" />
    </article>

    <div class="card section">
        <x-public.subscribe-form :newsletter="$newsletter" :heading="'Get the next issue of '.$newsletter->name" button="Subscribe" />
    </div>

    @include('public.partials.recommendations')
</x-public.layout>
