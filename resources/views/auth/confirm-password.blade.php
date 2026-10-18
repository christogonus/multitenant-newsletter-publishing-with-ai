<x-layouts::auth title="Confirm password">
    <x-slot:heading>Please confirm your password</x-slot:heading>
    <x-slot:subheading>This keeps your account safe before changing security settings.</x-slot:subheading>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-ui.input label="Password" name="password" type="password" autocomplete="current-password" required autofocus />
        <x-ui.button type="submit" size="lg" class="w-full">Confirm</x-ui.button>
    </form>
</x-layouts::auth>
