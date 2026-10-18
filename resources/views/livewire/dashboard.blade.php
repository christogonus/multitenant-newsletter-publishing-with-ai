<div class="space-y-6">
    @if (! $newsletter)
        <section class="relative overflow-hidden rounded-hero bg-brand px-6 py-12 text-white shadow-raised sm:px-12">
            <div class="max-w-xl">
                <p class="text-sm font-semibold tracking-wide text-white/80 uppercase">Welcome to NewsPub AI</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Your newsletter is about 10 minutes away.</h1>
                <p class="mt-3 text-base text-white/85">Pick a topic, and AI writes your first issue. You review it, press publish and share your link. No writing or tech skills needed.</p>
                <a href="{{ route('newsletters.create') }}" class="mt-8 inline-flex min-h-14 items-center gap-2 rounded-control bg-white px-8 text-base font-bold text-brand-ink shadow-card hover:bg-brand-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    Start My Newsletter <x-ui.icon name="arrow-right" />
                </a>
            </div>
        </section>

        <x-ui.card title="What happens next">
            <x-ui.stepper :steps="['Pick your niche', 'Review your first issue', 'Publish', 'Share your link']" :current="0" />
        </x-ui.card>
    @else
        @php
            $done = collect($checklist)->where('done', true)->count();
            $next = collect($checklist)->firstWhere('done', false);
        @endphp

        <x-ui.page-header :title="$newsletter->name" :description="$newsletter->tagline ?: 'A newsletter about '.$newsletter->niche" icon="newspaper">
            <x-slot:actions>
                <x-ui.button :href="route('issues.create')" icon="sparkles" size="lg">Write a new issue</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($done < count($checklist))
            <x-ui.card title="Get your newsletter live" :description="$done.' of '.count($checklist).' steps done'">
                <x-ui.progress :value="$done" :max="count($checklist)" class="mb-5" />
                <ol class="divide-y divide-line">
                    @foreach ($checklist as $step)
                        <li class="flex items-center gap-4 py-3">
                            <span @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-success text-white' => $step['done'],
                                'border border-line text-muted' => ! $step['done'],
                            ])>
                                @if ($step['done'])<x-ui.icon name="check" size="4" />@else{{ $loop->iteration }}@endif
                            </span>
                            <span @class(['flex-1 text-sm font-medium', 'text-muted line-through' => $step['done'], 'text-ink' => ! $step['done']])>{{ $step['label'] }}</span>
                            @if (! $step['done'])
                                <x-ui.button :href="$step['route']" :variant="$step === $next ? 'primary' : 'secondary'" size="sm">{{ $step['action'] }}</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Issues left this week" :value="$unlimited ? 'Unlimited' : $remaining.' of '.$weeklyLimit" icon="sparkles" area="create" />
            <x-ui.stat-card label="Readers" :value="number_format($readers)" icon="users" area="publish" />
            <x-ui.stat-card label="Live issues" :value="$liveIssues" icon="globe" area="grow" />
        </div>

        <x-ui.card title="Recent issues">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('issues.index')" icon-right="arrow-right">All issues</x-ui.button>
            </x-slot:actions>
            @if ($recentIssues->isEmpty())
                <x-ui.empty-state icon="newspaper" area="create" title="No issues yet" description="Your first issue takes about a minute to write.">
                    <x-ui.button :href="route('issues.create')" icon="sparkles">Write my first issue</x-ui.button>
                </x-ui.empty-state>
            @else
                <ul class="-my-2 divide-y divide-line">
                    @foreach ($recentIssues as $issue)
                        <li wire:key="recent-{{ $issue->id }}">
                            <a href="{{ route('issues.edit', $issue) }}" class="flex min-h-14 items-center gap-4 py-2 hover:text-brand">
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $issue->title }}</span>
                                <x-ui.pill :tone="$issue->status->tone()" :pulse="$issue->status === \App\Enums\IssueStatus::Generating">{{ $issue->status->label() }}</x-ui.pill>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($offer)
            <x-ui.locked-feature :product="$offer" compact />
        @endif
    @endif
</div>
