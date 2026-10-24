<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-page px-4 py-10 font-sans">
    <div class="absolute top-4 right-4"><x-ui.theme-toggle /></div>

    <a href="{{ url('/') }}" class="mb-8 text-lg font-bold text-ink">
        <img src="{{ asset('logo-dark-trim.webp') }}" alt="{{ config('app.name') }}" class="h-12 w-auto dark:hidden">
        <span class="hidden items-center gap-2 dark:flex">
            <img src="{{ asset('icon-square.webp') }}" alt="" class="size-9">
            {{ config('app.name') }}
        </span>
    </a>

    <div class="w-full max-w-md rounded-hero border border-line bg-card p-6 shadow-raised sm:p-8">
        @isset($heading)
            <h1 class="text-xl font-bold text-ink">{{ $heading }}</h1>
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
