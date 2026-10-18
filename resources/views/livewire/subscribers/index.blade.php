<div class="space-y-6">
    <x-ui.page-header title="Readers" description="People who signed up on your page." icon="users" area="publish">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('subscribers.export')" icon="download">Download list</x-ui.button>
            <x-ui.button area="publish" icon="plus" x-on:click="$dispatch('open-modal', 'add-reader')">Add a reader</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Total readers" :value="number_format($total)" icon="users" area="publish" />
        <x-ui.stat-card label="New this week" :value="number_format($thisWeek)" icon="sparkles" area="grow" />
    </div>

    @if ($total > 0)
        @php
            $peak = max(1, max(array_column($daily, 'count')));
            $sourceLabels = ['hosted_page' => 'Your page', 'lead_page' => 'Sign-up pages', 'added_by_owner' => 'Added by you'];
            $sourceMax = max(1, $sources->max());
        @endphp
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="New readers per day" description="Last 30 days" class="lg:col-span-2">
                <div class="flex h-40 items-end gap-0.5" role="img" aria-label="New readers per day for the last 30 days. Peak {{ $peak }} in a day.">
                    @foreach ($daily as $day)
                        <div class="group relative flex h-full flex-1 items-end" wire:key="day-{{ $day['date'] }}">
                            <div
                                @class(['w-full rounded-t-[4px] bg-publish transition-opacity group-hover:opacity-80', 'min-h-px' => $day['count'] > 0])
                                style="height: {{ $day['count'] > 0 ? max(2, round($day['count'] / $peak * 100)) : 0 }}%"
                            ></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded-control border border-line bg-card px-2 py-1 text-xs whitespace-nowrap text-ink shadow-raised group-hover:block">
                                {{ $day['label'] }}: <strong>{{ $day['count'] }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between border-t border-line pt-2 text-xs text-muted">
                    <span>{{ $daily[0]['label'] }}</span>
                    <span>Today</span>
                </div>
                <table class="sr-only">
                    <caption>New readers per day</caption>
                    <thead><tr><th>Day</th><th>New readers</th></tr></thead>
                    <tbody>
                        @foreach ($daily as $day)<tr><td>{{ $day['label'] }}</td><td>{{ $day['count'] }}</td></tr>@endforeach
                    </tbody>
                </table>
            </x-ui.card>

            <x-ui.card title="Where readers come from">
                <ul class="space-y-4">
                    @foreach ($sources as $source => $count)
                        <li>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-ink">{{ $sourceLabels[$source] ?? ucfirst(str_replace('_', ' ', $source)) }}</span>
                                <span class="font-semibold text-ink tabular-nums">{{ number_format($count) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-subtle">
                                <div class="h-full rounded-full bg-publish" style="width: {{ round($count / $sourceMax * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    @endif

    <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search by email" aria-label="Search readers" />

    <x-ui.table :headers="['Email', 'Joined from', 'Joined', 'Friends invited', '']" :empty="$subscribers->isEmpty()">
        <x-slot:emptyState>
            <x-ui.empty-state icon="users" area="publish" :title="$search ? 'No readers match that search' : 'No readers yet'" description="Share your page link and sign-ups appear here.">
                <x-ui.button :href="route('issues.index')" area="publish" icon="share">Find a link to share</x-ui.button>
            </x-ui.empty-state>
        </x-slot:emptyState>

        @foreach ($subscribers as $subscriber)
            <tr wire:key="subscriber-{{ $subscriber->id }}">
                <td class="px-4 py-3 font-medium text-ink">{{ $subscriber->email }}</td>
                <td class="px-4 py-3 text-muted">{{ str_replace('_', ' ', ucfirst($subscriber->source)) }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $subscriber->created_at->toFormattedDateString() }}</td>
                <td class="px-4 py-3 text-muted tabular-nums">{{ $subscriber->referrals_count }}</td>
                <td class="px-4 py-3 text-right">
                    <x-ui.button variant="ghost" size="sm" wire:click="unsubscribe({{ $subscriber->id }})" wire:confirm="Remove {{ $subscriber->email }} from your list?">Remove</x-ui.button>
                </td>
            </tr>
        @endforeach

        @if ($subscribers->hasPages())
            <x-slot:footer>{{ $subscribers->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.modal name="add-reader" title="Add a reader" max-width="md">
        <form wire:submit="add" class="space-y-4" id="add-reader-form">
            <x-ui.input wire:model="newEmail" type="email" label="Email" />
            <x-ui.checkbox wire:model="hasPermission" label="This person asked to get my newsletter." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
            <x-ui.button type="submit" form="add-reader-form" area="publish" loading="add">Add reader</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
