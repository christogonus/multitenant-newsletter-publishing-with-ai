<x-layouts::auth title="Sign in">
    <x-slot:heading>Welcome back</x-slot:heading>
    <x-slot:subheading>Sign in to keep growing your newsletter.</x-slot:subheading>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" autocomplete="email" required autofocus />
        <x-ui.input label="Password" name="password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4">
            <x-ui.checkbox label="Keep me signed in" name="remember" value="1" />
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand hover:underline">Forgot password?</a>
        </div>

        <x-ui.button type="submit" size="lg" class="w-full">Sign in</x-ui.button>

        <p class="text-center text-xs text-muted">
            By signing in you agree to the <a href="{{ route('legal.terms') }}" target="_blank" class="underline">terms</a>
            and <a href="{{ route('legal.privacy') }}" target="_blank" class="underline">privacy policy</a>.
        </p>
    </form>

    <x-slot:below>
        Just bought NewsPub AI? <a href="{{ route('support') }}" class="font-medium text-brand hover:underline">Get your login details</a>
    </x-slot:below>
</x-layouts::auth>
