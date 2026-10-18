<button
    type="button"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    x-on:click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
    {{ $attributes->class('inline-flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink') }}
    x-bind:aria-label="dark ? 'Switch to light theme' : 'Switch to dark theme'"
>
    <x-ui.icon name="moon" x-show="! dark" />
    <x-ui.icon name="sun" x-show="dark" x-cloak />
</button>
