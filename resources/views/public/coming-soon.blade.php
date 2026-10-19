{{-- Shown on every page of a publication while its owner is suspended. Branded, calm, and never says why. --}}
@php
    $accent = $theme['accent'];
    $onBackground = \App\Support\Theme::onColor($theme['background']) === '#ffffff' ? '#f8fafc' : $theme['text'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $newsletter->name }} · Coming soon</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box; background: {{ $theme['background'] }}; font-family: {!! $theme['font'] !!}; color: {{ $theme['text'] }}; text-align: center; }
        main { width: 100%; max-width: 520px; background: {{ $theme['surface'] }}; border-radius: {{ $theme['radius'] }}; padding: 48px 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); box-sizing: border-box; }
        .logo { max-height: 56px; max-width: 200px; margin: 0 auto 16px; display: block; }
        .name { margin: 0 0 24px; font-family: {!! $theme['heading_font'] !!}; font-size: 20px; font-weight: 700; color: {{ $theme['text'] }}; }
        .bar { width: 48px; height: 4px; border-radius: 4px; background: {{ $accent }}; margin: 0 auto 24px; }
        h1 { margin: 0 0 12px; font-family: {!! $theme['heading_font'] !!}; font-size: 30px; line-height: 1.2; }
        p { margin: 0; color: {{ $theme['muted'] }}; font-size: 17px; line-height: 1.6; }
        footer { margin-top: 20px; font-size: 13px; color: {{ $onBackground }}; opacity: .75; }
    </style>
</head>
<body>
    <div>
        <main>
            @if ($theme['logo_url'])
                <img class="logo" src="{{ $theme['logo_url'] }}" alt="{{ $newsletter->name }} logo">
            @endif
            <p class="name">{{ $newsletter->name }}</p>
            <div class="bar" aria-hidden="true"></div>
            <h1>Coming soon</h1>
            <p>We are getting a few things ready behind the scenes. Please check back shortly.</p>
        </main>
        <footer>{{ $newsletter->name }}</footer>
    </div>
</body>
</html>
