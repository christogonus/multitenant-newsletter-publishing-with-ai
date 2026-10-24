<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="relative flex min-h-full flex-col items-center justify-center overflow-x-hidden bg-page px-4 py-10 font-sans">
    <div class="pointer-events-none fixed -top-32 -left-24 size-96 rounded-full bg-publish/15 blur-3xl"></div>
    <div class="pointer-events-none fixed -right-24 -bottom-32 size-96 rounded-full bg-gold/20 blur-3xl"></div>
    <div class="pointer-events-none fixed top-1/3 right-1/4 size-64 rounded-full bg-create/10 blur-3xl"></div>

    <a href="{{ url('/') }}" class="relative mb-8">
        <img src="{{ asset('logo-dark-trim.webp') }}" alt="{{ config('app.name') }}" class="h-12 w-auto">
    </a>

    <div class="relative w-full max-w-md rounded-hero border border-line bg-card p-6 shadow-raised sm:p-8">
        @isset($heading)
            <h1 class="font-display text-2xl text-ink">{{ $heading }}</h1>
        @endisset
        @isset($subheading)
            <p class="mt-1 text-sm text-muted">{{ $subheading }}</p>
        @endisset

        @if (session('status'))
            <x-ui.alert tone="success" class="mt-5">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>
    </div>

    @isset($below)
        <div class="mt-6 text-center text-sm text-muted">{{ $below }}</div>
    @endisset

    <x-ui.toasts />

    @livewireScripts
</body>
</html>
