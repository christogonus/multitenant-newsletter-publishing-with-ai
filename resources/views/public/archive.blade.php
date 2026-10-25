<x-public.layout :newsletter="$newsletter" :theme="$theme" title="All newsletters">
    <main class="card">
        <h1 style="font-size: 28px;">All newsletters</h1>
        @if ($issues->isEmpty())
            <p class="muted">No newsletters yet.</p>
        @else
            <ul class="issue-list">
                @foreach ($issues as $issue)
                    <li>
                        <a class="title" href="{{ \App\Support\PublicLinks::route('issue', ['slug' => $issue->public_slug]) }}">{{ $issue->title }}</a>@if ($issue->is_paid_only && $newsletter->hasPaidSubscriptions()) <span class="small muted">&#128274; Paying readers</span>@endif
                        <div class="small muted">{{ $issue->published_at->toFormattedDateString() }}</div>
                    </li>
                @endforeach
            </ul>
            @if ($issues->hasPages())
                <p class="small" style="display: flex; justify-content: space-between; margin-top: 16px;">
                    @if ($issues->previousPageUrl())<a href="{{ $issues->previousPageUrl() }}">&larr; Newer</a>@else<span></span>@endif
                    @if ($issues->nextPageUrl())<a href="{{ $issues->nextPageUrl() }}">Older &rarr;</a>@endif
                </p>
            @endif
        @endif
    </main>
</x-public.layout>
