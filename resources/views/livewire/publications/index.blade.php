@php
    $money = fn ($amount) => '$'.number_format((float) $amount, 2);
@endphp

<div class="space-y-6">
    <x-ui.page-header title="All publications" description="Every publication you run, and what each one earns, in one place." icon="squares" area="automate">
        <x-slot:actions>
            @if ($publications->isNotEmpty())
                <x-ui.button variant="secondary" icon="currency-dollar" wire:click="openEarningForm">Log earnings</x-ui.button>
            @endif
            @if ($canCreate)
                <x-ui.button :href="route('newsletters.create')" area="automate" icon="plus" size="lg">New publication</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Publications" :value="$publications->count()" icon="squares" area="automate" />
        <x-ui.stat-card label="Readers across all" :value="number_format($totalReaders)" icon="users" area="publish" />
        <x-ui.stat-card label="Earned this month" :value="$money($earnedThisMonth)" icon="currency-dollar" area="earn" />
        <x-ui.stat-card label="Earned all time" :value="$money($earnedTotal)" icon="currency-dollar" area="earn" />
    </div>

    @if ($publications->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="newspaper" area="automate" title="No publications yet" description="Create your first publication in about a minute.">
                <x-ui.button :href="route('newsletters.create')" area="automate" icon="plus">Start a publication</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($publications as $publication)
                <x-ui.card wire:key="publication-{{ $publication->id }}" @class(['ring-2 ring-automate' => $publication->id === $currentId])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-ink">{{ $publication->name }}</h3>
                            <p class="truncate text-sm text-muted">{{ $publication->niche }}</p>
                        </div>
                        @if ($publication->id === $currentId)
                            <x-ui.badge tone="automate">Open now</x-ui.badge>
                        @endif
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">Readers</dt><dd class="font-semibold text-ink tabular-nums">{{ number_format($publication->subscribers_count) }}</dd></div>
                        <div><dt class="text-muted">Live newsletters</dt><dd class="font-semibold text-ink tabular-nums">{{ $publication->live_issues_count }}</dd></div>
                        <div><dt class="text-muted">Earned this month</dt><dd class="font-semibold text-ink tabular-nums">{{ $money($publication->earned_this_month) }}</dd></div>
                        <div><dt class="text-muted">Earned all time</dt><dd class="font-semibold text-ink tabular-nums">{{ $money($publication->earned_total) }}</dd></div>
                    </dl>
                    <x-slot:footer>
                        <x-ui.button variant="ghost" size="sm" :href="$publication->publicUrl()" target="_blank" rel="noopener" icon="eye">View page</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" icon="currency-dollar" wire:click="openEarningForm({{ $publication->id }})">Log earnings</x-ui.button>
                        @if ($publication->id !== $currentId)
                            <x-ui.button size="sm" area="automate" wire:click="switchTo({{ $publication->id }})">Open</x-ui.button>
                        @endif
                    </x-slot:footer>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card title="Recent earnings" description="Log each sponsor payment, month of paid subscriptions or promotion as it comes in.">
            @if ($recentEarnings->isEmpty())
                <x-ui.empty-state icon="currency-dollar" area="earn" title="Nothing logged yet" description="When a sponsor pays you, log it here to see which publication earns the most.">
                    <x-ui.button area="earn" icon="plus" wire:click="openEarningForm">Log your first earnings</x-ui.button>
                </x-ui.empty-state>
            @else
                <ul class="-my-2 divide-y divide-line">
                    @foreach ($recentEarnings as $earning)
                        <li wire:key="earning-{{ $earning->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm">
                            <span class="w-24 shrink-0 text-muted">{{ $earning->earned_on->toFormattedDateString() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $earning->description }}</span>
                                <span class="block truncate text-xs text-muted">{{ $earning->source->label() }} · {{ $earning->newsletter->name }}</span>
                            </span>
                            <span class="font-semibold text-ink tabular-nums">{{ $money($earning->amount) }}</span>
                            <x-ui.button variant="ghost" size="sm" wire:click="deleteEarning({{ $earning->id }})" wire:confirm="Remove this entry?" aria-label="Remove {{ $earning->description }}">Remove</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal name="earning" title="Log earnings" max-width="md">
        <form id="earning-form" wire:submit="logEarning" class="space-y-4">
            <x-ui.select wire:model="earningNewsletterId" label="Publication" :options="$newsletterOptions" />
            <x-ui.select wire:model="earningSource" label="Where it came from" :options="$sources" />
            <x-ui.input wire:model="earningDescription" label="Who paid" placeholder="For example, Acme Tools" maxlength="120" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="earningAmount" type="number" step="0.01" min="0.01" inputmode="decimal" label="Amount (USD)" icon="currency-dollar" />
                <x-ui.input wire:model="earningDate" type="date" label="Date received" :max="now()->toDateString()" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button type="submit" form="earning-form" area="earn" loading="logEarning">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
