<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-full bg-page font-sans">
    <header class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-6">
        <a href="{{ route('login') }}" class="flex items-center gap-2 text-lg font-bold text-ink">
            <span class="inline-flex size-9 items-center justify-center rounded-control bg-brand text-white"><x-ui.icon name="newspaper" size="5" /></span>
            NewsPub AI
        </a>
        <div class="flex items-center gap-2">
            <x-ui.theme-toggle />
            <x-ui.button variant="secondary" size="sm" :href="route('login')" icon-right="arrow-right">Sign in</x-ui.button>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-3xl px-4 pb-16">
        {{ $slot }}
    </main>

    <footer class="mx-auto flex w-full max-w-3xl flex-wrap gap-4 px-4 pb-10 text-xs text-muted">
        <a href="{{ route('legal.terms') }}" class="hover:underline">Terms</a>
        <a href="{{ route('legal.privacy') }}" class="hover:underline">Privacy</a>
    </footer>

    <x-ui.toasts />

    @livewireScripts
</body>
</html>
