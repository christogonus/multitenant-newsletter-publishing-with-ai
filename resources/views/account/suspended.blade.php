<x-layouts::auth title="Account on hold">
    <x-slot:heading>Your account is on hold</x-slot:heading>
    <x-slot:subheading>{{ auth()->user()->email }}</x-slot:subheading>

    <div class="space-y-4 text-sm text-muted">
        <p>We have paused your account. While it is on hold, your dashboard is closed and your publication pages show a “coming soon” message to visitors. Your newsletters, readers and settings are kept safe.</p>
        <p>To find out why, or to get your account back, email <a href="mailto:{{ config('newspub.support_email') }}" class="font-semibold text-brand underline">{{ config('newspub.support_email') }}</a>.</p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" class="w-full">Sign out</x-ui.button>
        </form>
    </div>
</x-layouts::auth>
