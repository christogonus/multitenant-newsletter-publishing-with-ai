<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('icon-square.webp') }}" type="image/webp">
<link rel="apple-touch-icon" href="{{ asset('icon-touch.png') }}">
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
