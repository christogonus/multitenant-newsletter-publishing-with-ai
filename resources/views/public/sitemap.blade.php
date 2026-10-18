{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ \App\Support\PublicLinks::route('home') }}</loc>
        @if ($issues->isNotEmpty())
            <lastmod>{{ $issues->first()->updated_at->toAtomString() }}</lastmod>
        @endif
    </url>
    <url>
        <loc>{{ \App\Support\PublicLinks::route('archive') }}</loc>
    </url>
    @foreach ($issues as $issue)
        <url>
            <loc>{{ \App\Support\PublicLinks::route('issue', ['slug' => $issue->public_slug]) }}</loc>
            <lastmod>{{ $issue->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
