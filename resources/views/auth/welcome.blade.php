<x-layouts::auth title="Welcome">
    <x-slot:heading>Welcome to {{ config('app.name') }}, {{ $user->name }}</x-slot:heading>
    <x-slot:subheading>Choose a password and you will be writing your first newsletter in a few minutes.</x-slot:subheading>

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        <x-ui.input label="Email" type="email" :value="$user->email" disabled />
        <x-ui.input label="Password" name="password" type="password" autocomplete="new-password" hint="At least 8 characters." required autofocus />
        <x-ui.input label="Type it again" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-ui.checkbox name="terms" value="1">
            <x-slot:label>
                I agree to the <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-brand underline">terms</a>
                and <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-brand underline">privacy policy</a>.
            </x-slot:label>
        </x-ui.checkbox>
        <x-ui.button type="submit" size="lg" class="w-full" icon-right="arrow-right">Start my newsletter</x-ui.button>
    </form>
</x-layouts::auth>
