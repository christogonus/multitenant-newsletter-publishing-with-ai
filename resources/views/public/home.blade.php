<x-public.layout :newsletter="$newsletter" :theme="$theme" :canonical="$newsletter->publicUrl()">
    <main class="card">
        <h1 style="font-size: 30px;">{{ $newsletter->tagline ?: 'A newsletter about '.$newsletter->niche }}</h1>
        <p class="muted">Join readers who get {{ $newsletter->name }} {{ strtolower($newsletter->frequency->label()) }}. Free, and you can leave any time.</p>
        <x-public.subscribe-form :newsletter="$newsletter" button="Subscribe for free" />
    </main>

    <section class="section">
        <h2 class="on-bg" style="font-size: 20px;">Latest newsletters</h2>
        @if ($issues->isEmpty())
            <div class="card"><p class="muted" style="margin: 0;">The first newsletter is on its way. Subscribe so you do not miss it.</p></div>
        @else
            <div class="card" style="padding-top: 8px; padding-bottom: 8px;">
                <ul class="issue-list">
                    @foreach ($issues as $issue)
                        <li @if ($loop->first) style="border-top: 0;" @endif>
                            <a class="title" href="{{ \App\Support\PublicLinks::route('issue', ['slug' => $issue->public_slug]) }}">{{ $issue->title }}</a>@if ($issue->is_paid_only && $newsletter->hasPaidSubscriptions()) <span class="small muted">&#128274; Paying readers</span>@endif
                            <div class="small muted">{{ $issue->published_at->toFormattedDateString() }}@if ($issue->preheader) · {{ $issue->preheader }}@endif</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @include('public.partials.recommendations')
</x-public.layout>
