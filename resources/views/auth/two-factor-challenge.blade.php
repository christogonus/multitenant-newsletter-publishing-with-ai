<x-layouts::auth title="Two-step sign in">
    <x-slot:heading>One more step</x-slot:heading>
    <x-slot:subheading>Enter the 6-digit code from your authenticator app.</x-slot:subheading>

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
            @csrf
            <div x-show="! recovery">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input label="Backup code" name="recovery_code" autocomplete="one-time-code" />
            </div>
            <x-ui.button type="submit" size="lg" class="w-full">Sign in</x-ui.button>
        </form>
        <button type="button" class="mt-4 min-h-11 text-sm font-medium text-brand hover:underline" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">Use a backup code instead</span>
            <span x-show="recovery" x-cloak>Use my authenticator app</span>
        </button>
    </div>
</x-layouts::auth>
