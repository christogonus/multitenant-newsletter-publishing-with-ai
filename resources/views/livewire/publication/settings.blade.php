<div class="space-y-6">
    <x-ui.page-header title="Publication settings" description="How your publication is named, written and found." icon="adjustments" area="publish" />

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Basics">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input wire:model="name" label="Publication name" />
                <x-ui.input wire:model="tagline" label="Tagline" hint="One line under your name on your page." optional />
                <x-ui.input wire:model="niche" label="Topic" />
                <x-ui.segmented wire:model="frequency" label="How often" :options="$frequencies" />
            </div>
        </x-ui.card>

        <x-ui.card title="How the AI writes" description="These notes guide every newsletter.">
            <div class="space-y-5">
                <x-ui.input wire:model="audience" label="Who is it for?" optional />
                <x-ui.textarea wire:model="voice" label="Voice and style" rows="3" optional placeholder="For example: warm and funny, short sentences, uses everyday examples" />
            </div>
        </x-ui.card>

        <x-ui.card title="Web address">
            <x-ui.field label="Your page" for="settings-subdomain" :error="$errors->first('subdomain')" hint="Changing this breaks links you have already shared.">
                <div class="flex min-h-11 items-stretch overflow-hidden rounded-control border border-line bg-card focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30">
                    <input id="settings-subdomain" wire:model="subdomain" class="min-w-0 flex-1 border-0 bg-transparent px-3 text-base text-ink focus:ring-0 focus:outline-none sm:text-sm" />
                    <span class="flex items-center bg-subtle px-3 text-sm text-muted">.{{ $publicRoot }}</span>
                </div>
            </x-ui.field>
        </x-ui.card>

        <x-ui.card title="Send automatically">
            @if ($canAutoPublish)
                <x-ui.checkbox wire:model="autoPublish" label="When I publish, also send it through my connected email services" />
            @else
                <x-ui.locked-feature :product="$deliveryProduct" compact />
            @endif
        </x-ui.card>

        <div class="flex justify-end">
            <x-ui.button type="submit" size="lg" loading="save">Save settings</x-ui.button>
        </div>
    </form>

    <x-ui.card title="Delete this publication" class="border-danger/40">
        <p class="text-sm text-muted">Removes the page, every newsletter and every reader. This cannot be undone.</p>
        <x-ui.button variant="danger" class="mt-4" x-on:click="$dispatch('open-modal', 'delete-publication')" icon="trash">Delete publication</x-ui.button>
    </x-ui.card>

    <x-ui.modal name="delete-publication" title="Delete {{ $name }}?" max-width="md">
        <x-ui.input wire:model="confirmName" label="Type the publication name to confirm" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button variant="danger" wire:click="deletePublication" loading="deletePublication">Delete forever</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
