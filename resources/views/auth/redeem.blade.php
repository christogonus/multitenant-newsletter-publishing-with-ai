<x-layouts::auth :title="'Activate '.$brand">
    <x-slot:heading>Activate your {{ $brand }} account</x-slot:heading>
    <x-slot:subheading>Enter the access code you received after buying, then choose your sign-in details.</x-slot:subheading>

    <form method="POST" action="{{ route('redeem.store', $reseller?->reseller_slug ? ['reseller' => $reseller->reseller_slug] : []) }}" class="space-y-5">
        @csrf
        <x-ui.input label="Access code" name="code" :value="old('code', $code)" placeholder="NPAI-XXXX-XXXX-XXXX" autocomplete="off" required autofocus class="font-mono uppercase" />
        <x-ui.input label="Your name" name="name" :value="old('name')" autocomplete="name" required />
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" autocomplete="email" required />
        <x-ui.input label="Password" name="password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
        <x-ui.input label="Type it again" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-ui.checkbox name="terms" value="1">
            <x-slot:label>
                I agree to the <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-brand underline">terms</a>
                and <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-brand underline">privacy policy</a>.
            </x-slot:label>
        </x-ui.checkbox>
        <x-ui.button type="submit" size="lg" class="w-full" icon-right="arrow-right">Activate my account</x-ui.button>
    </form>

    <x-slot:below>
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">Sign in</a>
    </x-slot:below>
</x-layouts::auth>
