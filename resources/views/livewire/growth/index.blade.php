<div class="space-y-6">
    <x-ui.page-header title="Grow your readers" description="Sign-up pages with a free gift, and rewards for readers who invite friends." icon="gift" area="grow" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Sign-up pages" :value="$leadPages->count()" icon="document-text" area="grow" />
        <x-ui.stat-card label="Readers from sign-up pages" :value="$leadPages->sum('subscribers_count')" icon="users" area="grow" />
        <x-ui.stat-card label="Readers from referrals" :value="$referredTotal" icon="share" area="grow" />
    </div>

    <x-ui.card title="Make a sign-up page" description="The AI writes the page and a free gift readers get for joining.">
        <form wire:submit="createLeadPage" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <x-ui.input wire:model="giftIdea" label="Gift idea" optional placeholder="For example: a 7-day budgeting checklist" class="flex-1" hint="Leave empty and the AI picks the best gift for your readers." />
            <x-ui.button type="submit" area="grow" icon="sparkles" class="sm:mt-7" loading="createLeadPage">Create my page</x-ui.button>
        </form>

        @if ($leadPages->isNotEmpty())
            <ul class="mt-6 divide-y divide-line">
                @foreach ($leadPages as $leadPage)
                    <li wire:key="lead-{{ $leadPage->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-ink">{{ $leadPage->headline }}</p>
                            <p class="text-xs text-muted">{{ $leadPage->views }} views · {{ $leadPage->subscribers_count }} sign-ups</p>
                        </div>
                        <x-ui.pill :tone="$leadPage->is_live ? 'success' : 'neutral'">{{ $leadPage->is_live ? 'Live' : 'Hidden' }}</x-ui.pill>
                        <x-ui.button variant="secondary" size="sm" :href="route('growth.pages.edit', $leadPage)">Edit</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Referral rewards" description="After subscribing, readers get a personal link. When friends join with it, they earn these rewards. You send the rewards yourself.">
        <form wire:submit="saveTiers" class="space-y-3">
            @foreach ($tiers as $index => $tier)
                <div wire:key="tier-{{ $index }}" class="grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-start">
                    <x-ui.input wire:model="tiers.{{ $index }}.referrals" type="number" min="1" label="Friends" />
                    <x-ui.input wire:model="tiers.{{ $index }}.reward" label="Reward" />
                    <x-ui.button variant="ghost" class="sm:mt-7" wire:click="removeTier({{ $index }})" icon="trash" aria-label="Remove reward"><span class="sr-only">Remove</span></x-ui.button>
                </div>
            @endforeach
            <div class="flex flex-wrap justify-between gap-2 pt-2">
                <x-ui.button variant="secondary" wire:click="addTier" icon="plus">Add a reward</x-ui.button>
                <x-ui.button type="submit" area="grow" loading="saveTiers">Save rewards</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Top referrers">
        @if ($topReferrers->isEmpty())
            <x-ui.empty-state icon="share" area="grow" title="No referrals yet" description="Readers see their link right after they subscribe. Mention your rewards in your next issue to get things going." />
        @else
            <ul class="divide-y divide-line">
                @foreach ($topReferrers as $referrer)
                    <li class="flex items-center justify-between py-3 text-sm">
                        <span class="text-ink">{{ $referrer->email }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ $referrer->referrals_count }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="{{ $playbook['title'] }}" description="{{ $playbook['intro'] }}">
        <div class="space-y-3">
            @foreach ($playbook['stages'] as $stage)
                <details class="group rounded-card border border-line" @if ($loop->first) open @endif>
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm font-semibold text-ink">
                        <span>{{ $stage['title'] }}</span>
                        <x-ui.icon name="chevron-down" size="4" class="text-muted transition group-open:rotate-180" />
                    </summary>
                    <ol class="list-inside list-decimal space-y-2 px-4 pb-4 text-sm text-muted">
                        @foreach ($stage['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </div>
    </x-ui.card>
</div>
