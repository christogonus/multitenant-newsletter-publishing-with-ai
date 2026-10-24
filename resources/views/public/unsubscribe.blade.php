<x-public.layout :newsletter="$newsletter" :theme="$theme" title="Your subscription">
    <main class="card">
        <h1 style="font-size: 28px;">Your subscription</h1>
        <p class="muted">{{ $subscriber->email }}</p>

        @if ($done)
            <p><strong>{{ $done }}</strong></p>
        @endif

        @if (! $subscriber->unsubscribed_at)
            <form method="POST" action="{{ $unsubscribeAction }}" class="section">
                @csrf
                <p>Stop getting this newsletter. You can join again any time.</p>
                <button type="submit" class="btn secondary">Unsubscribe</button>
            </form>
        @endif

        <form method="POST" action="{{ $deleteAction }}" class="section">
            @csrf
            <p>Erase your email and sign-up details from this newsletter for good.</p>
            <button type="submit" class="btn secondary">Delete my details</button>
        </form>

        <p class="section small muted">If this newsletter was sent through an email service, also use the unsubscribe link at the bottom of the email.</p>
    </main>
</x-public.layout>
