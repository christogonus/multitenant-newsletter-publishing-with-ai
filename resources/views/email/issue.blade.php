{{-- Email-safe HTML: table layout, inline styles, no scripts. Used by HTML export and platform drivers. --}}
@php
    $font = $theme['font'];
    $headingFont = $theme['heading_font'];
    $text = $theme['text'];
    $muted = $theme['muted'];
    $accent = $theme['accent'];
    $onAccent = \App\Support\Theme::onColor($accent);
    $surface = $theme['surface'];
    $background = $theme['background'];
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $issue->subject_line ?: $issue->title }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $background }};">
    @if ($issue->preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $issue->preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:{{ $background }};">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;">
                    <tr>
                        <td align="{{ $theme['header'] === 'center' ? 'center' : 'left' }}" style="padding:16px 24px;{{ $theme['header'] === 'band' ? 'background:'.$accent.';' : '' }}font-family:{!! $headingFont !!};font-size:26px;font-weight:bold;color:{{ $theme['header'] === 'band' ? $onAccent : (\App\Support\Theme::onColor($background) === '#ffffff' ? '#f8fafc' : $text) }};">
                            @if ($theme['logo_url'])
                                <img src="{{ $theme['logo_url'] }}" alt="{{ $newsletter->name }}" style="max-height:48px;max-width:200px;display:block;margin-bottom:8px;border:0;">
                            @endif
                            {{ $newsletter->name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:{{ $surface }};padding:32px 28px;border-radius:{{ $theme['radius'] }};font-family:{!! $font !!};font-size:17px;line-height:1.6;color:{{ $text }};">
                            <h1 style="margin:0 0 16px;font-family:{!! $headingFont !!};font-size:28px;line-height:1.25;color:{{ $text }};">{{ $issue->title }}</h1>
                            @foreach ($blocks as $block)
                                @switch($block['type'] ?? null)
                                    @case('heading')
                                        <h2 style="margin:28px 0 10px;font-family:{!! $headingFont !!};font-size:22px;line-height:1.3;color:{{ $text }};">{{ $block['text'] }}</h2>
                                        @break
                                    @case('paragraph')
                                        <p style="margin:0 0 16px;">{!! nl2br(e($block['text'])) !!}</p>
                                        @break
                                    @case('bullets')
                                        <ul style="margin:0 0 16px;padding-left:22px;">
                                            @foreach ($block['items'] ?? [] as $item)
                                                @if (trim($item) !== '')<li style="margin:0 0 6px;">{{ $item }}</li>@endif
                                            @endforeach
                                        </ul>
                                        @break
                                    @case('link')
                                        @if (! empty($block['url']))
                                            <p style="margin:0 0 16px;"><a href="{{ $block['url'] }}" style="color:{{ $accent }};font-weight:bold;">{{ $block['text'] ?: $block['url'] }} &rarr;</a>@if (! empty($block['description']))<br><span style="color:{{ $muted }};font-size:15px;">{{ $block['description'] }}</span>@endif</p>
                                        @endif
                                        @break
                                    @case('image')
                                        @if (! empty($block['url']))
                                            <p style="margin:0 0 16px;"><img src="{{ $block['url'] }}" alt="{{ $block['alt'] ?? '' }}" width="544" style="width:100%;max-width:544px;height:auto;border:0;display:block;">@if (! empty($block['caption']))<span style="display:block;color:{{ $muted }};font-size:14px;margin-top:6px;">{{ $block['caption'] }}</span>@endif</p>
                                        @endif
                                        @break
                                    @case('quote')
                                        <p style="margin:0 0 16px;padding-left:14px;border-left:4px solid {{ $accent }};font-style:italic;">{{ $block['text'] }}@if (! empty($block['cite']))<br><span style="font-style:normal;color:{{ $muted }};font-size:14px;">— {{ $block['cite'] }}</span>@endif</p>
                                        @break
                                    @case('divider')
                                        <hr style="border:0;border-top:1px solid #e5e7eb;margin:28px 0;">
                                        @break
                                @endswitch
                            @endforeach
                            @if ($locked)
                                <p style="margin:24px 0 0;font-weight:bold;">The rest of this issue is for paying readers.</p>
                            @endif
                            @if ($issue->public_slug)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;">
                                    <tr>
                                        <td style="background:{{ $accent }};border-radius:{{ $theme['radius'] }};">
                                            <a href="{{ $issue->publicUrl() }}" style="display:inline-block;padding:12px 20px;font-family:{!! $font !!};font-size:15px;font-weight:bold;color:{{ $onAccent }};text-decoration:none;">{{ $locked ? 'Continue reading' : 'Read on the web' }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 24px;font-family:{!! $font !!};font-size:13px;line-height:1.5;color:{{ \App\Support\Theme::onColor($background) === '#ffffff' ? '#cbd5e1' : $muted }};">
                            You are getting this because you subscribed to {{ $newsletter->name }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
