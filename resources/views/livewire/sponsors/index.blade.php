<div class="space-y-6">
    <x-ui.page-header title="Sponsors and money" description="Find sponsors, show them why you are worth it and know what to charge." icon="currency-dollar" area="earn" />

    <div role="tablist" class="flex gap-1 overflow-x-auto rounded-control bg-subtle p-1">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class(['min-h-11 rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap', 'bg-card text-ink shadow-card' => $tab === $key, 'text-muted hover:text-ink' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'directory')
        <x-ui.alert tone="warning">Programmes change often. Check each one's current rules before you apply.</x-ui.alert>
        <x-ui.select wire:model.live="kind" label="Type" :options="$kinds->mapWithKeys(fn ($kind) => [$kind => $kind])->all()" placeholder="All types" class="sm:max-w-xs" />
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($sponsors as $sponsor)
                <x-ui.card wire:key="sponsor-{{ $sponsor->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-ink">{{ $sponsor->name }}</h3>
                            <x-ui.badge tone="earn" class="mt-1">{{ $sponsor->kind }}</x-ui.badge>
                        </div>
                        <x-ui.button variant="secondary" size="sm" :href="$sponsor->website" target="_blank" rel="noopener" icon-right="external">Visit</x-ui.button>
                    </div>
                    <p class="mt-3 text-sm text-muted">{{ $sponsor->description }}</p>
                    <p class="mt-3 text-xs text-muted">Good for: {{ implode(', ', $sponsor->niches ?? []) }} · Minimum readers: {{ $sponsor->minimum_audience }}</p>
                </x-ui.card>
            @endforeach
        </div>
    @elseif ($tab === 'media-kit')
        <x-ui.card title="Your media kit" description="A one-page summary sponsors ask for. Built from your real numbers.">
            <x-slot:actions>
                <x-ui.button wire:click="generateMediaKit" area="earn" icon="sparkles" loading="generateMediaKit">{{ $mediaKit ? 'Write it again' : 'Write my media kit' }}</x-ui.button>
            </x-slot:actions>
            @if ($mediaKit)
                @php $kit = $mediaKit->content; @endphp
                <article class="space-y-4 text-sm">
                    <h3 class="text-xl font-bold text-ink">{{ $kit['headline'] }}</h3>
                    <p class="text-ink">{{ $kit['about'] }}</p>
                    <div><p class="font-semibold text-ink">Who reads it</p><p class="text-muted">{{ $kit['audience'] }}</p></div>
                    <div>
                        <p class="font-semibold text-ink">Why sponsor us</p>
                        <ul class="mt-1 list-inside list-disc text-muted">@foreach ($kit['why_sponsor'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($kit['packages'] as $package)
                            <div class="rounded-card border border-line p-4">
                                <p class="font-semibold text-ink">{{ $package['name'] }} · {{ $package['price'] }}</p>
                                <p class="text-muted">{{ $package['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </article>
            @else
                <x-ui.empty-state icon="document-text" area="earn" title="No media kit yet" description="Press the button and the AI writes one from your readers and issues." />
            @endif
        </x-ui.card>
    @elseif ($tab === 'outreach')
        <x-ui.alert>Replace the words in {curly brackets} before sending.</x-ui.alert>
        @foreach ($outreach as $template)
            <x-ui.card :title="$template['title']" wire:key="outreach-{{ $loop->index }}">
                <x-ui.copy-field :value="$template['subject']" label="Subject" />
                <div class="mt-4" x-data="{ copied: false }">
                    <p class="mb-1.5 text-sm font-medium text-ink">Email</p>
                    <pre class="rounded-control bg-subtle p-4 font-sans text-sm whitespace-pre-wrap text-ink">{{ $template['body'] }}</pre>
                    <x-ui.button variant="secondary" size="sm" class="mt-2" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($template['body'])); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copied' : 'Copy email'">Copy email</span>
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endforeach
    @elseif ($tab === 'rates')
        <x-ui.card title="What to charge" description="Starting prices based on your readers. Raise them as you grow.">
            <x-ui.input wire:model.live.debounce.300ms="audienceSize" type="number" min="0" label="Readers" class="sm:max-w-xs" />
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($packages as $package)
                    <x-ui.stat-card :label="$package['name']" :value="$package['price']" :hint="$package['description']" icon="currency-dollar" area="earn" />
                @endforeach
            </div>
            <p class="mt-4 text-sm text-muted">Based on about {{ number_format($assumptions['opens']) }} people opening each issue and ${{ $assumptions['cpm_low'] }} to ${{ $assumptions['cpm_high'] }} per 1,000 opens, which is typical for focused newsletters.</p>
        </x-ui.card>
    @else
        <x-ui.card title="Start paid subscriptions" description="Earn from your most loyal readers.">
            <ol class="space-y-5">
                @foreach ($paidGuide as $step)
                    <li class="flex gap-4">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-earn text-sm font-semibold text-white">{{ $loop->iteration }}</span>
                        <div><p class="text-sm font-semibold text-ink">{{ $step['title'] }}</p><p class="text-sm text-muted">{{ $step['body'] }}</p></div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif
</div>
