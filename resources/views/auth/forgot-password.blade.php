<x-layouts::auth title="Forgot password">
    <x-slot:heading>Forgot your password?</x-slot:heading>
    <x-slot:subheading>Enter your email and we will send you a link to choose a new one.</x-slot:subheading>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" autocomplete="email" required autofocus />
        <x-ui.button type="submit" size="lg" class="w-full">Send me a link</x-ui.button>
    </form>

    <x-slot:below>
        <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">Back to sign in</a>
    </x-slot:below>
</x-layouts::auth>
