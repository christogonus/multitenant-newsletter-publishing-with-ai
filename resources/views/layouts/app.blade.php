@php
    $user = auth()->user();
    $sections = app(\App\Support\Navigation::class)->sections($user);
    $currentNewsletter = $user->activeNewsletter();
    $showSwitcher = $user->owns(\App\Enums\Product::Oto9Multi) || $user->newsletters()->count() > 1;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-full font-sans" x-data="{ nav: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[70] focus:rounded-control focus:bg-card focus:px-4 focus:py-2">Skip to content</a>

    {{-- Mobile nav backdrop --}}
    <div x-show="nav" x-transition.opacity x-cloak class="fixed inset-0 z-30 bg-ink/50 lg:hidden" x-on:click="nav = false"></div>

    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line bg-card transition-transform lg:translate-x-0"
        x-bind:class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Main"
    >
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-line px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-ink">
                <span class="inline-flex size-8 items-center justify-center rounded-control bg-brand text-white"><x-ui.icon name="newspaper" size="5" /></span>
                NewsPub AI
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            @foreach ($sections as $section)
                <div @class(['mt-5' => ! $loop->first])>
                    @if ($section['label'])
                        <p class="px-3 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">{{ $section['label'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            @php $isActive = request()->routeIs($item['active']); @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'group flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium transition',
                                        'bg-subtle text-ink' => $isActive,
                                        'text-muted hover:bg-subtle hover:text-ink' => ! $isActive && ! $item['locked'],
                                        'text-muted/80 hover:bg-subtle' => $item['locked'],
                                    ])
                                    @if ($isActive) aria-current="page" @endif
                                >
                                    <span @class([
                                        'inline-flex size-7 items-center justify-center rounded-[6px]',
                                        \App\Support\Ui::tint($section['area']),
                                        'opacity-60' => $item['locked'],
                                    ])>
                                        <x-ui.icon :name="$item['icon']" size="4" />
                                    </span>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if ($item['locked'])
                                        <x-ui.icon name="lock-closed" size="4" class="text-muted" />
                                        <span class="sr-only">(upgrade)</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-line px-3 py-3">
            <a href="{{ route('upgrades') }}" class="flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-semibold text-brand hover:bg-brand-tint">
                <x-ui.icon name="sparkles" size="4" /> See all upgrades
            </a>
            <a href="{{ route('support') }}" class="flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium text-muted hover:bg-subtle hover:text-ink">
                <x-ui.icon name="question" size="4" /> Help
            </a>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-card/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="-ml-2 inline-flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle lg:hidden" x-on:click="nav = true" aria-label="Open menu">
                <x-ui.icon name="bars-3" size="6" />
            </button>

            @if ($showSwitcher && $currentNewsletter)
                <x-ui.dropdown align="left" width="w-72">
                    <x-slot:trigger>
                        <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-control border border-line bg-card px-3 text-sm font-semibold text-ink hover:bg-subtle">
                            <x-ui.icon name="squares" size="4" class="text-automate" />
                            <span class="max-w-40 truncate">{{ $currentNewsletter->name }}</span>
                            <x-ui.icon name="chevron-down" size="4" class="text-muted" />
                        </button>
                    </x-slot:trigger>
                    @foreach ($user->newsletters()->orderBy('name')->get() as $publication)
                        <form method="POST" action="{{ route('publications.switch', $publication) }}">
                            @csrf
                            <x-ui.dropdown-item type="submit" :icon="$publication->is($currentNewsletter) ? 'check' : 'newspaper'">{{ $publication->name }}</x-ui.dropdown-item>
                        </form>
                    @endforeach
                    @if (\Illuminate\Support\Facades\Route::has('publications.index'))
                        <div class="my-1 border-t border-line"></div>
                        <x-ui.dropdown-item :href="route('publications.index')" icon="squares">Manage publications</x-ui.dropdown-item>
                    @endif
                </x-ui.dropdown>
            @elseif ($currentNewsletter)
                <span class="hidden truncate text-sm font-semibold text-ink sm:inline">{{ $currentNewsletter->name }}</span>
            @endif

            <div class="ml-auto flex items-center gap-1">
                @if ($currentNewsletter)
                    <x-ui.button variant="ghost" size="sm" :href="$currentNewsletter->publicUrl()" target="_blank" rel="noopener" icon="eye" class="hidden sm:inline-flex">View my page</x-ui.button>
                @endif
                <x-ui.theme-toggle />
                <x-ui.dropdown>
                    <x-slot:trigger>
                        <button type="button" class="inline-flex size-11 items-center justify-center rounded-full text-muted hover:bg-subtle" aria-label="Account menu">
                            <x-ui.icon name="user" size="6" />
                        </button>
                    </x-slot:trigger>
                    <div class="border-b border-line px-4 py-3">
                        <p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p>
                        <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                    </div>
                    <x-ui.dropdown-item :href="route('account')" icon="adjustments">Account</x-ui.dropdown-item>
                    @if ($user->is_admin)
                        <x-ui.dropdown-item :href="route('admin.users')" icon="shield">Admin</x-ui.dropdown-item>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.dropdown-item type="submit" icon="logout">Sign out</x-ui.dropdown-item>
                    </form>
                </x-ui.dropdown>
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toasts />

    {{-- Livewire only injects its assets (and Alpine with them) when a component renders, so plain views like the locked screen need them too. --}}
    @livewireScripts
</body>
</html>
