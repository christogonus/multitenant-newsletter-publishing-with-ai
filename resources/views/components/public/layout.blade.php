@props(['newsletter', 'theme', 'title' => null, 'description' => null, 'canonical' => null, 'reportIssueId' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.$newsletter->name : $newsletter->name }}</title>
    {{-- Only the owner's logo, never the app's icon: publications carry the owner's brand alone. --}}
    <link rel="icon" href="{{ $theme['logo_url'] ?: 'data:,' }}">
    @if ($theme['logo_url'])
        <link rel="apple-touch-icon" href="{{ $theme['logo_url'] }}">
    @endif
    <meta name="description" content="{{ $description ?? $newsletter->tagline ?? 'A newsletter about '.$newsletter->niche }}">
    <meta property="og:title" content="{{ $title ?? $newsletter->name }}">
    <meta property="og:description" content="{{ $description ?? $newsletter->tagline ?? $newsletter->niche }}">
    <meta property="og:type" content="{{ $title ? 'article' : 'website' }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $newsletter->name }}" href="{{ \App\Support\PublicLinks::route('feed') }}">
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:url" content="{{ $canonical }}">
    @endif
    <style>
        :root {
            --bg: {{ $theme['background'] }};
            --surface: {{ $theme['surface'] }};
            --text: {{ $theme['text'] }};
            --muted: {{ $theme['muted'] }};
            --accent: {{ $theme['accent'] }};
            --on-accent: {{ \App\Support\Theme::onColor($theme['accent']) }};
            --radius: {{ $theme['radius'] }};
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: {!! $theme['font'] !!}; font-size: 18px; line-height: 1.65; -webkit-font-smoothing: antialiased; }
        a { color: var(--accent); }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
        h1, h2, h3 { font-family: {!! $theme['heading_font'] !!}; line-height: 1.25; margin: 0 0 .5em; }
        .wrap { max-width: 680px; margin: 0 auto; padding: 24px 16px 64px; }
        .card { background: var(--surface); border-radius: var(--radius); padding: 32px 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        @media (min-width: 640px) { .card { padding: 40px 48px; } }
        .masthead { padding: 32px 0 24px; text-align: {{ $theme['header'] === 'center' ? 'center' : 'left' }}; }
        .masthead.band { background: var(--accent); color: var(--on-accent); border-radius: var(--radius); padding: 32px 24px; margin-bottom: 24px; }
        .masthead.band a, .masthead.band .tagline { color: var(--on-accent); }
        .masthead .name { font-family: {!! $theme['heading_font'] !!}; font-size: 30px; font-weight: 800; text-decoration: none; color: inherit; }
        .masthead .tagline { color: var(--muted); margin: 4px 0 0; font-size: 16px; }
        .masthead img.logo { max-height: 56px; max-width: 220px; display: inline-block; margin-bottom: 8px; }
        .bg-dark .masthead:not(.band) .name, .bg-dark .masthead:not(.band) .tagline { color: #f8fafc; }
        .muted { color: var(--muted); }
        .small { font-size: 14px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 22px; border: 0; border-radius: var(--radius); background: var(--accent); color: var(--on-accent); font: inherit; font-weight: 700; font-size: 16px; text-decoration: none; cursor: pointer; }
        .btn.secondary { background: transparent; color: var(--accent); border: 2px solid var(--accent); }
        .field { display: block; width: 100%; min-height: 48px; padding: 10px 14px; border: 1px solid rgba(0,0,0,.2); border-radius: var(--radius); font: inherit; font-size: 16px; background: #fff; color: #111; }
        .subscribe { display: grid; gap: 10px; margin-top: 16px; }
        @media (min-width: 640px) { .subscribe.inline { grid-template-columns: 1fr auto; } }
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .consent { font-size: 13px; color: var(--muted); margin-top: 8px; }
        .error { color: #b91c1c; font-size: 14px; margin: 6px 0 0; }
        .issue-list { list-style: none; padding: 0; margin: 0; }
        .issue-list li { padding: 18px 0; border-top: 1px solid rgba(0,0,0,.08); }
        .issue-list a.title { font-family: {!! $theme['heading_font'] !!}; font-size: 21px; font-weight: 700; text-decoration: none; color: var(--text); }
        .issue-list a.title:hover { color: var(--accent); }
        .content p { margin: 0 0 1.1em; }
        .content h2 { font-size: 24px; margin-top: 1.6em; }
        .content ul { padding-left: 1.2em; margin: 0 0 1.1em; }
        .content li { margin-bottom: .4em; }
        .content blockquote { margin: 1.4em 0; padding: .2em 0 .2em 1em; border-left: 4px solid var(--accent); font-style: italic; }
        .content figure { margin: 1.4em 0; }
        .content img { max-width: 100%; height: auto; border-radius: var(--radius); }
        .content figcaption { font-size: 14px; color: var(--muted); margin-top: 6px; }
        .content hr { border: 0; border-top: 1px solid rgba(0,0,0,.12); margin: 2em 0; }
        .link-card { display: block; padding: 14px 18px; border: 1px solid rgba(0,0,0,.12); border-radius: var(--radius); text-decoration: none; margin: 0 0 1.1em; }
        .link-card strong { color: var(--accent); }
        .link-card span { display: block; color: var(--muted); font-size: 15px; }
        .share { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .share a { font-size: 15px; min-height: 44px; display: inline-flex; align-items: center; padding: 0 14px; border-radius: var(--radius); border: 1px solid rgba(0,0,0,.15); text-decoration: none; color: var(--text); background: var(--surface); }
        .section { margin-top: 24px; }
        .recommend { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 10px; }
        .recommend li a { display: block; padding: 14px 18px; border-radius: var(--radius); border: 1px solid rgba(0,0,0,.1); text-decoration: none; color: var(--text); background: var(--surface); }
        footer.site { text-align: center; font-size: 13px; color: var(--muted); margin-top: 40px; }
        .bg-dark footer.site, .bg-dark footer.site a, .bg-dark .on-bg { color: #cbd5e1; }
        details.report summary { cursor: pointer; display: inline; }
        details.report form { margin-top: 12px; display: grid; gap: 8px; text-align: left; }
    </style>
</head>
<body @class(['bg-dark' => \App\Support\Theme::onColor($theme['background']) === '#ffffff'])>
    <div class="wrap">
        <header @class(['masthead', 'band' => $theme['header'] === 'band'])>
            @if ($theme['logo_url'])
                <div><img class="logo" src="{{ $theme['logo_url'] }}" alt="{{ $newsletter->name }} logo"></div>
            @endif
            <a class="name" href="{{ \App\Support\PublicLinks::route('home') }}">{{ $newsletter->name }}</a>
            @if ($newsletter->tagline)
                <p class="tagline">{{ $newsletter->tagline }}</p>
            @endif
        </header>

        {{ $slot }}

        <footer class="site">
            <p>
                <a href="{{ \App\Support\PublicLinks::route('archive') }}">All issues</a>
                ·
                <details class="report">
                    <summary>Report this page</summary>
                    <form method="POST" action="{{ \App\Support\PublicLinks::route('report') }}">
                        <input type="hidden" name="issue" value="{{ $reportIssueId ?? '' }}">
                        <select name="reason" class="field" required aria-label="Reason">
                            <option value="">Why are you reporting this?</option>
                            <option value="spam">Spam or scam</option>
                            <option value="harmful">Harmful or hateful content</option>
                            <option value="copyright">Copies my work</option>
                            <option value="other">Something else</option>
                        </select>
                        <textarea name="details" class="field" rows="3" maxlength="2000" placeholder="Anything else we should know? (optional)" aria-label="Details"></textarea>
                        <input type="email" name="email" class="field" maxlength="255" placeholder="Your email (optional)" aria-label="Your email">
                        <div class="hp" aria-hidden="true"><label>Leave empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <button type="submit" class="btn secondary">Send report</button>
                    </form>
                </details>
            </p>
            <p>Made with {{ config('app.name') }}</p>
        </footer>
    </div>
</body>
</html>
