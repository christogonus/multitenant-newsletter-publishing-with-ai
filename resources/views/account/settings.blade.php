<x-layouts::app title="Account">
    @php
        $user = auth()->user();
        $twoFactorPending = $user->two_factor_secret && ! $user->two_factor_confirmed_at;
        $twoFactorOn = $user->two_factor_secret && $user->two_factor_confirmed_at;
        $onStartingPassword = $user->uses_default_password;
        $isSharedDemo = $user->is_demo;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Account" description="Your sign-in details and security." icon="user" />

        @if (session('status') === 'profile-information-updated')
            <x-ui.alert tone="success">Your details were saved.</x-ui.alert>
        @elseif (session('status') === 'password-updated')
            <x-ui.alert tone="success">Your password was changed.</x-ui.alert>
        @elseif (session('status') === 'two-factor-authentication-confirmed')
            <x-ui.alert tone="success">Two-step sign in is on.</x-ui.alert>
        @elseif (session('status') === 'demo-account-locked')
            <x-ui.alert tone="warning">{{ \App\Http\Middleware\ProtectDemoAccount::MESSAGE }}</x-ui.alert>
        @elseif (session('status') === 'own-password-required')
            <x-ui.alert tone="warning">Choose your own password first. It needs a code from your email, so only you can do it.</x-ui.alert>
        @endif

        @if ($isSharedDemo)
            <x-ui.alert tone="info" title="Shared demo account">Other people use this account too, so its name, email, password and two-step sign in cannot be changed, and it cannot be deleted. Everything else works as it does for a buyer.</x-ui.alert>
        @endif

        <x-ui.card title="Your details">
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-ui.input name="name" label="Name" :value="old('name', $user->name)" required />
                @if ($isSharedDemo)
                    <x-ui.input name="email" type="email" label="Email" :value="$user->email" readonly required />
                @elseif ($onStartingPassword)
                    <x-ui.input name="email" type="email" label="Email" :value="$user->email" hint="You can change your email after you choose your own password." readonly required />
                @else
                    <x-ui.input name="email" type="email" label="Email" :value="old('email', $user->email)" hint="The email you sign in with. Your purchases stay linked to this account if you change it." required />
                @endif
                @unless ($isSharedDemo)
                    <div class="sm:col-span-2 flex justify-end"><x-ui.button type="submit">Save details</x-ui.button></div>
                @endunless
            </form>
        </x-ui.card>

        @if ($isSharedDemo)
            <x-ui.card title="Password">
                <p class="text-sm text-muted">Shared demo accounts keep the password they were given.</p>
            </x-ui.card>
        @elseif ($onStartingPassword)
            <livewire:account.choose-own-password />
        @else
            <x-ui.card title="Password">
                <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 sm:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <x-ui.input name="current_password" type="password" label="Current password" autocomplete="current-password" />
                    <x-ui.input name="password" type="password" label="New password" autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" type="password" label="Type it again" autocomplete="new-password" />
                    <div class="sm:col-span-3 flex justify-end"><x-ui.button type="submit">Change password</x-ui.button></div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card title="Two-step sign in" description="Ask for a code from your phone when you sign in. Keeps your account safe even if your password leaks.">
            @if ($isSharedDemo)
                <p class="text-sm text-muted">Not available on shared demo accounts.</p>
            @elseif ($onStartingPassword)
                <p class="text-sm text-muted">Available once you choose your own password.</p>
            @elseif ($twoFactorOn)
                <x-ui.alert tone="success">Two-step sign in is on.</x-ui.alert>
                <details class="mt-4">
                    <summary class="min-h-11 cursor-pointer text-sm font-medium text-brand">Show my backup codes</summary>
                    <ul class="mt-3 grid gap-1 rounded-control bg-subtle p-4 font-mono text-sm sm:grid-cols-2">
                        @foreach ($user->recoveryCodes() as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </details>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="secondary">Turn off</x-ui.button>
                </form>
            @elseif ($twoFactorPending)
                <p class="text-sm text-ink">Scan this with an authenticator app (such as Google Authenticator), then type the 6-digit code.</p>
                <div class="mt-4 inline-block rounded-card bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                    @csrf
                    <x-ui.input name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" />
                    <x-ui.button type="submit" class="sm:mt-7">Turn on</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <x-ui.button type="submit" icon="shield">Set up two-step sign in</x-ui.button>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="What you own">
            <ul class="flex flex-wrap gap-2">
                @foreach (\App\Enums\Product::cases() as $product)
                    @if ($user->owns($product))
                        <li><x-ui.badge :tone="$product->area()->value" icon="check">{{ $product->label() }}</x-ui.badge></li>
                    @endif
                @endforeach
            </ul>
            <x-ui.button class="mt-4" variant="ghost" :href="route('upgrades')" icon-right="arrow-right">See all upgrades</x-ui.button>
        </x-ui.card>

        <x-ui.card title="Delete my account" class="border-danger/40">
            <p class="text-sm text-muted">This erases your account, every publication, every newsletter and every reader. It cannot be undone. Your purchases cannot be used again after this.</p>
            @if ($isSharedDemo)
                <p class="mt-4 text-sm text-muted">Not available on shared demo accounts.</p>
            @elseif ($onStartingPassword)
                <p class="mt-4 text-sm text-muted">Available once you choose your own password.</p>
            @else
                <x-ui.button variant="danger" class="mt-4" icon="trash" x-data x-on:click="$dispatch('open-modal', 'delete-account')">Delete my account</x-ui.button>
            @endif
        </x-ui.card>

        <x-ui.modal name="delete-account" title="Delete your account?" max-width="md">
            <form method="POST" action="{{ route('account.destroy') }}" id="delete-account-form" class="space-y-4">
                @csrf
                @method('DELETE')
                <x-ui.input name="password" type="password" label="Your password" autocomplete="current-password" />
                <x-ui.input name="confirm" label="Type DELETE to confirm" autocomplete="off" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Keep my account</x-ui.button>
                <x-ui.button type="submit" form="delete-account-form" variant="danger">Delete everything</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        @if ($errors->has('password') || $errors->has('confirm'))
            <div x-data x-init="$dispatch('open-modal', 'delete-account')"></div>
        @endif
    </div>
</x-layouts::app>
