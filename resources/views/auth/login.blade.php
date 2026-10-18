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
    </form>

    <x-slot:below>
        Bought NewsPub AI? Check your inbox for the welcome email to choose your password.
    </x-slot:below>
</x-layouts::auth>
