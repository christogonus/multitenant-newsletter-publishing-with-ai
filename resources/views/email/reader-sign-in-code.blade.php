{{-- Sent in the newsletter's own name and branding: readers should only ever see the owner's business, never the platform. --}}
@php
    $font = $theme['font'];
    $headingFont = $theme['heading_font'];
    $text = $theme['text'];
    $muted = $theme['muted'];
    $accent = $theme['accent'];
    $onAccent = \App\Support\Theme::onColor($accent);
    $surface = $theme['surface'];
    $background = $theme['background'];
    $onBackground = \App\Support\Theme::onColor($background) === '#ffffff' ? '#f8fafc' : $text;
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Your sign-in code for {{ $newsletter->name }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $background }};">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your code is {{ $code }}. It works for {{ $minutes }} minutes.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:{{ $background }};">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="520" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:520px;">
                    <tr>
                        <td align="{{ $theme['header'] === 'center' ? 'center' : 'left' }}" style="padding:16px 24px;{{ $theme['header'] === 'band' ? 'background:'.$accent.';' : '' }}font-family:{!! $headingFont !!};font-size:22px;font-weight:bold;color:{{ $theme['header'] === 'band' ? $onAccent : $onBackground }};">
                            @if ($theme['logo_url'])
                                <img src="{{ $theme['logo_url'] }}" alt="{{ $newsletter->name }}" style="max-height:44px;max-width:180px;display:block;margin-bottom:8px;border:0;">
                            @endif
                            {{ $newsletter->name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:{{ $surface }};padding:32px 28px;border-radius:{{ $theme['radius'] }};font-family:{!! $font !!};font-size:16px;line-height:1.6;color:{{ $text }};">
                            <p style="margin:0 0 16px;">Enter this code to keep reading {{ $newsletter->name }}:</p>
                            <p style="margin:0 0 20px;font-family:{!! $headingFont !!};font-size:34px;font-weight:bold;letter-spacing:6px;color:{{ $text }};">{{ $code }}</p>
                            <p style="margin:0;color:{{ $muted }};font-size:14px;">It works for {{ $minutes }} minutes. If you did not ask for it, you can ignore this email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 24px;font-family:{!! $font !!};font-size:13px;line-height:1.5;color:{{ \App\Support\Theme::onColor($background) === '#ffffff' ? '#cbd5e1' : $muted }};">
                            Sent by {{ $newsletter->name }} because someone entered this email on its website.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
