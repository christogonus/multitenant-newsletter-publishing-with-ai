@php
    $defaultPassword = (string) config('newspub.default_password');
    $loginDetails = 'Sign in at: '.route('login')."\nEmail: the email you used at checkout"
        .($defaultPassword !== '' ? "\nPassword: ".$defaultPassword : '');
@endphp

<x-layouts::page title="Your login details">
    <div class="space-y-6">
        <div class="py-4 text-center">
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Welcome to {{ config('app.name') }}</h1>
            <p class="mx-auto mt-2 max-w-xl text-muted">Your login details, a quick setup guide, and how to reach us if you get stuck.</p>
        </div>

        <x-ui.card title="Your login details" description="Thank you for your purchase. Use these details to sign in.">
            <div class="space-y-4">
                <x-ui.copy-field label="Sign-in page" :value="route('login')" />

                <div>
                    <p class="mb-1.5 text-sm font-medium text-ink">Email</p>
                    <p class="rounded-control border border-line bg-subtle px-3 py-2.5 text-sm text-ink">The email address you used at checkout</p>
                </div>

                @if ($defaultPassword !== '')
                    <x-ui.copy-field label="Password" :value="$defaultPassword" />
                    <x-ui.alert tone="warning">Everyone starts with this password. After you sign in, open Account from the menu at the top right and choose your own. We email you a code to confirm it is you.</x-ui.alert>
                @else
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-ink">Password</p>
                        <p class="text-sm text-muted">Use the password you chose from your welcome email. No email? Use <a href="{{ route('password.request') }}" class="font-medium text-brand underline">Forgot password</a> with the email you used at checkout.</p>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 pt-1" x-data="{ copied: false }">
                    <x-ui.button :href="route('login')" icon-right="arrow-right">Sign in now</x-ui.button>
                    <x-ui.button variant="secondary" icon="clipboard" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($loginDetails) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                        <span x-text="copied ? 'Copied' : 'Copy all'">Copy all</span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>

        @include('partials.support')
    </div>
</x-layouts::page>
