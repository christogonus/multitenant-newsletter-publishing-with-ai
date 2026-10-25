<div class="space-y-6">
    <x-ui.page-header title="Autopilot" description="Plan a month of newsletters, write them in one go, and let them go live on their dates." icon="calendar" area="automate">
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="planMonth" icon="sparkles" loading="planMonth">Plan my month</x-ui.button>
            <x-ui.button wire:click="writeAll" area="automate" icon="bolt" loading="writeAll">Write them all</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $unlimited)
        <x-ui.alert>This publication has {{ $remaining }} newsletters left this week. Planned newsletters beyond that are written as the week rolls on, or you can upgrade to Unlimited Creation.</x-ui.alert>
    @endif

    <x-ui.card title="Add a topic">
        <form wire:submit="addItem" class="grid gap-3 sm:grid-cols-[1fr_11rem_8rem_auto] sm:items-start">
            <x-ui.input wire:model="newTopic" label="Topic" placeholder="What should this newsletter cover?" />
            <x-ui.input wire:model="newDate" type="date" label="Date" />
            <x-ui.input wire:model="publishTime" type="time" label="Time" />
            <x-ui.button type="submit" variant="secondary" class="sm:mt-7" icon="plus" loading="addItem">Add</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Coming up" :padding="false">
        @if ($items->isEmpty())
            <x-ui.empty-state icon="calendar" area="automate" title="Your calendar is empty" description="Press Plan my month and the AI suggests a topic for every newsletter day.">
                <x-ui.button wire:click="planMonth" area="automate" icon="sparkles">Plan my month</x-ui.button>
            </x-ui.empty-state>
        @else
            <ol class="divide-y divide-line">
                @foreach ($items as $date => $dayItems)
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row">
                        <div class="w-28 shrink-0">
                            <p class="text-sm font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($date)->format('D j M') }}</p>
                        </div>
                        <ul class="flex-1 space-y-2">
                            @foreach ($dayItems as $item)
                                <li wire:key="item-{{ $item->id }}" class="flex flex-wrap items-center gap-3">
                                    <span class="text-xs text-muted tabular-nums">{{ $item->scheduled_for->format('g:ia') }}</span>
                                    <span class="min-w-0 flex-1 text-sm text-ink">{{ $item->topic }}</span>
                                    @if ($item->issue)
                                        <x-ui.pill :tone="$item->issue->status->tone()" :pulse="$item->issue->status === \App\Enums\IssueStatus::Generating">{{ $item->issue->status->label() }}</x-ui.pill>
                                        <x-ui.button variant="ghost" size="sm" :href="route('issues.edit', $item->issue)">Review</x-ui.button>
                                    @else
                                        <x-ui.badge>Planned</x-ui.badge>
                                        <x-ui.button variant="ghost" size="sm" wire:click="removeItem({{ $item->id }})" icon="trash" aria-label="Remove"><span class="sr-only">Remove</span></x-ui.button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-ui.card>
</div>
