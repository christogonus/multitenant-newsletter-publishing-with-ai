<x-layouts::auth title="Choose a new password">
    <x-slot:heading>Choose a new password</x-slot:heading>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input label="Email" name="email" type="email" :value="old('email', $request->email)" autocomplete="email" required />
        <x-ui.input label="New password" name="password" type="password" autocomplete="new-password" hint="At least 8 characters." required autofocus />
        <x-ui.input label="Type it again" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-ui.button type="submit" size="lg" class="w-full">Save password</x-ui.button>
    </form>
</x-layouts::auth>
