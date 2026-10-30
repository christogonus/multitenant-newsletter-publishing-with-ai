<x-ui.card title="Change email" description="The email you sign in with and where password resets go. Your purchases stay linked to this account.">
    @if ($pendingEmail === null)
        <form wire:submit="sendCode" class="grid gap-5 sm:grid-cols-2">
            <x-ui.input wire:model="email" type="email" label="New email" autocomplete="email" />
            <x-ui.input wire:model="password" type="password" label="Current password" autocomplete="current-password" />
            <div class="flex justify-end sm:col-span-2">
                <x-ui.button type="submit" icon="envelope" loading="sendCode">Email me a code</x-ui.button>
            </div>
        </form>
    @else
        <p class="text-sm text-muted">We sent a 6-digit code to <span class="font-medium text-ink">{{ $pendingEmail }}</span>. Your email stays the same until you type it here.</p>
        <form wire:submit="confirm" class="mt-4 grid gap-5 sm:grid-cols-2">
            <x-ui.input wire:model="code" label="Code from the email" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
            <div class="flex flex-wrap items-center justify-end gap-3 sm:col-span-2">
                <x-ui.button variant="ghost" wire:click="startOver" loading="startOver">Start over</x-ui.button>
                <x-ui.button type="submit" loading="confirm">Confirm new email</x-ui.button>
            </div>
        </form>
    @endif
</x-ui.card>
