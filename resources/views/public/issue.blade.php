<x-public.layout :newsletter="$newsletter" :theme="$theme" :title="$issue->title" :description="$issue->preheader" :canonical="$issue->publicUrl()" :report-issue-id="$issue->id">
    <article class="card">
        <p class="small muted" style="margin: 0 0 8px;">{{ $issue->published_at->toFormattedDateString() }}</p>
        <h1 style="font-size: 32px;">{{ $issue->title }}</h1>

        @if ($locked)
            <x-public.blocks :blocks="$issue->teaserBlocks()" />

            <div class="section" style="position: relative; margin-top: 8px; padding: 28px 24px; border: 2px solid var(--accent); border-radius: var(--radius); text-align: center;">
                <p style="font-size: 28px; margin: 0;" aria-hidden="true">&#128274;</p>
                <h2 style="font-size: 22px; margin: 8px 0;">The rest of this newsletter is for paying readers</h2>
                @if ($newsletter->paid_perks)
                    <p class="muted">{{ \Illuminate\Support\Str::limit($newsletter->paid_perks, 240) }}</p>
                @endif
                <p style="margin-bottom: 8px;"><a class="btn" href="{{ \App\Support\PublicLinks::route('members', ['next' => $issue->public_slug]) }}">Continue reading</a></p>
                <p class="small muted" style="margin: 0;">Already paying? Use the same button to sign in with your email.</p>
            </div>
        @else
            <x-public.blocks :blocks="$issue->blockList()" />
        @endif

        <hr style="border: 0; border-top: 1px solid rgba(0,0,0,.12); margin: 32px 0 8px;">
        <p class="small muted" style="margin-bottom: 0;">Enjoyed this? Pass it on.</p>
        <x-public.share :url="$issue->publicUrl()" :text="$issue->title" />
    </article>

    <div class="card section">
        <x-public.subscribe-form :newsletter="$newsletter" :heading="'Get the next newsletter from '.$newsletter->name" button="Subscribe" />
    </div>

    @include('public.partials.recommendations')
</x-public.layout>
