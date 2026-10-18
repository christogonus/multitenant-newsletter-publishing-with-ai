<x-layouts::auth title="Your subscription">
    <x-slot:heading>{{ $subscriber->newsletter->name }}</x-slot:heading>
    <x-slot:subheading>{{ $subscriber->email }}</x-slot:subheading>

    @if ($done)
        <x-ui.alert tone="success">{{ $done }}</x-ui.alert>
    @endif

    <div class="mt-5 space-y-6">
        @if (! $subscriber->unsubscribed_at)
            <form method="POST" action="{{ $unsubscribeAction }}">
                @csrf
                <p class="mb-3 text-sm text-muted">Stop getting this newsletter. You can join again any time.</p>
                <x-ui.button type="submit" variant="secondary" class="w-full">Unsubscribe</x-ui.button>
            </form>
        @endif

        <form method="POST" action="{{ $deleteAction }}">
            @csrf
            <p class="mb-3 text-sm text-muted">Erase your email and sign-up details from this newsletter for good.</p>
            <x-ui.button type="submit" variant="danger" class="w-full">Delete my details</x-ui.button>
        </form>

        <p class="text-sm text-muted">If this newsletter was sent through an email service, also use the unsubscribe link at the bottom of the email.</p>
    </div>
</x-layouts::auth>
