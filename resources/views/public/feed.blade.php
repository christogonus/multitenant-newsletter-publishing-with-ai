{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $newsletter->name }}</title>
        <link>{{ \App\Support\PublicLinks::route('home') }}</link>
        <atom:link href="{{ \App\Support\PublicLinks::route('feed') }}" rel="self" type="application/rss+xml" />
        <description>{{ $newsletter->tagline ?: 'A newsletter about '.$newsletter->niche }}</description>
        <language>en</language>
        @if ($issues->isNotEmpty())
            <lastBuildDate>{{ $issues->first()->published_at->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($issues as $issue)
            @php $url = \App\Support\PublicLinks::route('issue', ['slug' => $issue->public_slug]); @endphp
            <item>
                <title>{{ $issue->title }}</title>
                <link>{{ $url }}</link>
                <guid isPermaLink="true">{{ $url }}</guid>
                <pubDate>{{ $issue->published_at->toRssString() }}</pubDate>
                <description>{{ $issue->preheader ?: \Illuminate\Support\Str::limit($issue->plainText(), 300) }}</description>
                <content:encoded xmlns:content="http://purl.org/rss/1.0/modules/content/"><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', \App\Support\IssueRenderer::contentHtml($issue)) !!}]]></content:encoded>
            </item>
        @endforeach
    </channel>
</rss>
