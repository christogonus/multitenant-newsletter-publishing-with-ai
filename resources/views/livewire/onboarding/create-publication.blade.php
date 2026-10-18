<div class="space-y-6">
    <x-ui.page-header title="Start your newsletter" description="Two quick steps. You can change everything later." icon="sparkles" area="create" />

    <x-ui.stepper :steps="['Pick a topic', 'Name it', 'First issue']" :current="$step - 1" area="create" />

    @if ($step === 1)
        <x-ui.card title="What will your newsletter be about?" description="Choose a popular topic or type your own.">
            <x-ui.input wire:model.live.debounce.250ms="search" icon="search" placeholder="Search topics, for example money or fitness" aria-label="Search topics" />

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->niches as $option)
                    <button
                        type="button"
                        wire:key="niche-{{ $option['key'] }}"
                        wire:click="chooseNiche('{{ $option['key'] }}')"
                        @class([
                            'flex min-h-20 items-start gap-3 rounded-card border p-4 text-left transition hover:border-create hover:bg-create-tint focus-visible:outline-2 focus-visible:outline-create',
                            'border-create bg-create-tint' => $niche === $option['label'],
                            'border-line bg-card' => $niche !== $option['label'],
                        ])
                    >
                        <x-ui.area-chip area="create" :icon="$option['icon']" size="sm" />
                        <span>
                            <span class="block text-sm font-semibold text-ink">{{ $option['label'] }}</span>
                            <span class="mt-0.5 block text-sm text-muted">{{ $option['description'] }}</span>
                        </span>
                    </button>
                @empty
                    <p class="text-sm text-muted sm:col-span-2 lg:col-span-3">No matching topics. Type your own below.</p>
                @endforelse
            </div>

            <div class="mt-6 border-t border-line pt-6">
                <form wire:submit="useCustomNiche" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <x-ui.input wire:model="customNiche" label="Or type your own topic" placeholder="For example: vegan baking, golf tips, local history" class="flex-1" />
                    <x-ui.button type="submit" variant="secondary" class="sm:mt-7" loading="useCustomNiche">Use this topic</x-ui.button>
                </form>
            </div>
        </x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="Name your newsletter" class="lg:col-span-2">
                <form wire:submit="create" class="space-y-5" id="create-publication">
                    <x-ui.input wire:model.live.debounce.300ms="name" label="Newsletter name" hint="Short and memorable works best." />
                    <x-ui.input wire:model="audience" label="Who is it for?" hint="One sentence. This helps the AI write for the right readers." optional />
                    <x-ui.segmented wire:model.live="frequency" label="How often?" :options="$frequencies" />
                    <x-ui.field label="Your web address" for="subdomain" :error="$errors->first('subdomain')" hint="This is where readers find your newsletter.">
                        <div class="flex min-h-11 items-stretch overflow-hidden rounded-control border border-line bg-card focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30">
                            <input id="subdomain" wire:model.live.debounce.400ms="subdomain" class="min-w-0 flex-1 border-0 bg-transparent px-3 text-base text-ink focus:ring-0 focus:outline-none sm:text-sm" />
                            <span class="flex items-center bg-subtle px-3 text-sm text-muted">.{{ $publicRoot }}</span>
                        </div>
                    </x-ui.field>
                </form>
                <x-slot:footer>
                    <x-ui.button variant="ghost" wire:click="back" icon="arrow-left">Change topic</x-ui.button>
                    <x-ui.button type="submit" form="create-publication" size="lg" area="create" icon="sparkles" loading="create">Create and write my first issue</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Your newsletter" class="h-fit">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Topic</dt>
                        <dd class="font-semibold text-ink">{{ $niche }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="font-semibold text-ink">{{ $name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Goes out</dt>
                        <dd class="font-semibold text-ink">{{ $frequencies[$frequency] ?? '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Web address</dt>
                        <dd class="font-mono text-sm break-all text-ink">{{ $subdomain ?: 'your-name' }}.{{ $publicRoot }}</dd>
                    </div>
                </dl>
                <x-ui.alert class="mt-5">The AI writes your first issue as soon as you press create. You review it before anything goes live.</x-ui.alert>
            </x-ui.card>
        </div>
    @endif
</div>
