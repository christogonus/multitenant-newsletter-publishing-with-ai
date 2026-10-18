<div class="space-y-6">
    <x-ui.page-header :title="$headline ?: 'Sign-up page'" icon="gift" area="grow" :back="route('growth.index')">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="$url" target="_blank" rel="noopener" icon="eye">View page</x-ui.button>
            <x-ui.button wire:click="save" area="grow" loading="save">Save</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Share this link">
        <x-ui.copy-field :value="$url" size="lg" />
    </x-ui.card>

    <x-ui.card title="The page">
        <div class="space-y-5">
            <x-ui.input wire:model="headline" label="Headline" />
            <x-ui.input wire:model="subheadline" label="Sub-headline" optional />
            <x-ui.textarea wire:model="bulletsText" label="Benefits" rows="4" hint="One per line." />
            <x-ui.input wire:model="buttonLabel" label="Button text" />
            <x-ui.checkbox wire:model="isLive" label="Page is live" hint="Turn off to hide the page without deleting it." />
        </div>
    </x-ui.card>

    <x-ui.card title="The free gift" description="Readers see this right after they sign up.">
        <div class="space-y-5">
            <x-ui.input wire:model="magnetTitle" label="Gift title" />
            <x-ui.textarea wire:model="magnetText" label="Gift content" rows="16" hint="Leave a blank line between paragraphs. Start a line with ## to make it a heading." />
        </div>
    </x-ui.card>

    <div class="flex justify-between">
        <x-ui.button variant="ghost" wire:click="delete" wire:confirm="Delete this sign-up page?" icon="trash">Delete page</x-ui.button>
        <x-ui.button wire:click="save" area="grow" size="lg" loading="save">Save</x-ui.button>
    </div>
</div>
