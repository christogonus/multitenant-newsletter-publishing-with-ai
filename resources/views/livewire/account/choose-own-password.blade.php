<x-ui.card title="Password" description="You are signed in with the starting password every new member gets. Choose your own so only you can sign in.">
    @if (! $codeSent)
        <p class="text-sm text-muted">To prove this is your account, we will email a 6-digit code to <span class="font-medium text-ink">{{ auth()->user()->email }}</span>.</p>
        @error('code')<p class="mt-3 text-sm text-danger">{{ $message }}</p>@enderror
        <x-ui.button class="mt-4" icon="envelope" wire:click="sendCode" loading="sendCode">Email me a code</x-ui.button>
    @else
        <form wire:submit="save" class="grid gap-5 sm:grid-cols-3">
            <x-ui.input wire:model="code" label="Code from the email" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
            <x-ui.input wire:model="password" type="password" label="New password" autocomplete="new-password" hint="At least 8 characters." />
            <x-ui.input wire:model="password_confirmation" type="password" label="Type it again" autocomplete="new-password" />
            <div class="flex flex-wrap items-center justify-end gap-3 sm:col-span-3">
                <x-ui.button variant="ghost" wire:click="sendCode" loading="sendCode">Send a new code</x-ui.button>
                <x-ui.button type="submit" loading="save">Change password</x-ui.button>
            </div>
        </form>
    @endif
</x-ui.card>
