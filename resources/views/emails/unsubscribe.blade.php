<x-layouts::page title="Email preferences">
    <x-ui.card class="mx-auto max-w-lg">
        @if ($done)
            <x-ui.alert tone="success" title="You are unsubscribed">{{ $user->email }} will not get {{ config('app.name') }} announcement emails any more. Emails about your account and purchases still arrive.</x-ui.alert>
        @else
            <h1 class="font-display text-2xl text-ink">Unsubscribe from announcements?</h1>
            <p class="mt-2 text-sm text-muted">{{ $user->email }} will stop getting {{ config('app.name') }} announcement emails. Emails about your account and purchases still arrive.</p>
            <form method="POST" action="{{ $action }}" class="mt-5">
                @csrf
                <x-ui.button type="submit">Unsubscribe</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</x-layouts::page>
