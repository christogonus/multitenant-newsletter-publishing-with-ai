<x-layouts::app title="Account">
    @php
        $user = auth()->user();
        $twoFactorPending = $user->two_factor_secret && ! $user->two_factor_confirmed_at;
        $twoFactorOn = $user->two_factor_secret && $user->two_factor_confirmed_at;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Account" description="Your sign-in details and security." icon="user" />

        @if (session('status') === 'profile-information-updated')
            <x-ui.alert tone="success">Your details were saved.</x-ui.alert>
        @elseif (session('status') === 'password-updated')
            <x-ui.alert tone="success">Your password was changed.</x-ui.alert>
        @elseif (session('status') === 'two-factor-authentication-confirmed')
            <x-ui.alert tone="success">Two-step sign in is on.</x-ui.alert>
        @endif

        <x-ui.card title="Your details">
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-ui.input name="name" label="Name" :value="old('name', $user->name)" required />
                <x-ui.input name="email" type="email" label="Email" :value="old('email', $user->email)" required />
                <div class="sm:col-span-2 flex justify-end"><x-ui.button type="submit">Save details</x-ui.button></div>
            </form>
        </x-ui.card>

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

        <x-ui.card title="Two-step sign in" description="Ask for a code from your phone when you sign in. Keeps your account safe even if your password leaks.">
            @if ($twoFactorOn)
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
    </div>
</x-layouts::app>
