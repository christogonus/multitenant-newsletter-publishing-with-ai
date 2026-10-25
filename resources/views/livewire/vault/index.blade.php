<div class="space-y-6">
    <x-ui.page-header title="Niche and Swipe Vault" description="The research and swipe files that make starting easier." icon="archive" area="create" />

    <nav class="flex gap-1 overflow-x-auto rounded-control bg-subtle p-1 sm:w-fit" aria-label="Vault sections">
        @foreach ($sections as $key => $label)
            <a href="{{ route('vault.index', ['section' => $key]) }}" @if ($section === $key) aria-current="page" @endif
                @class(['inline-flex min-h-11 items-center rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap', 'bg-card text-ink shadow-card' => $section === $key, 'text-muted hover:text-ink' => $section !== $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($section === 'niches')
        <x-ui.table :headers="['#', 'Niche', 'Who reads it', 'Sponsor money', 'Competition', 'First newsletter idea']" :empty="empty($vault['niches'])">
            <x-slot:emptyState><x-ui.empty-state icon="archive" area="create" title="Coming soon" /></x-slot:emptyState>
            @foreach ($vault['niches'] as $niche)
                <tr>
                    <td class="px-4 py-3 font-semibold text-ink tabular-nums">{{ $niche['rank'] }}</td>
                    <td class="px-4 py-3"><p class="font-medium text-ink">{{ $niche['niche'] }}</p><p class="text-xs text-muted">{{ $niche['why'] }}</p></td>
                    <td class="px-4 py-3 text-muted">{{ $niche['audience'] }}</td>
                    <td class="px-4 py-3"><x-ui.badge :tone="match ($niche['money']) { 'High' => 'success', 'Medium' => 'warning', default => 'neutral' }">{{ $niche['money'] }}</x-ui.badge></td>
                    <td class="px-4 py-3"><x-ui.badge :tone="match ($niche['competition']) { 'Low' => 'success', 'Medium' => 'warning', default => 'danger' }">{{ $niche['competition'] }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-muted">{{ $niche['first_issue_idea'] }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @elseif ($section === 'subject-lines')
        <x-ui.alert>Swap the words in {curly brackets} for your own. Use one in your next newsletter's subject line.</x-ui.alert>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($vault['subject_lines'] as $group)
                <x-ui.card :title="$group['category']">
                    <ul class="space-y-1">
                        @foreach ($group['lines'] as $line)
                            <li x-data="{ copied: false }" class="flex items-center gap-2">
                                <span class="flex-1 text-sm text-ink">{{ $line }}</span>
                                <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink sm:size-9" x-on:click="navigator.clipboard.writeText(@js($line)); copied = true; setTimeout(() => copied = false, 1500)" aria-label="Copy subject line">
                                    <x-ui.icon name="clipboard" size="4" x-show="! copied" />
                                    <x-ui.icon name="check" size="4" x-show="copied" x-cloak class="text-success" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @else
        <x-ui.card title="Your first 30 days" description="One small task a day. Most take under 30 minutes.">
            <ol class="divide-y divide-line">
                @foreach ($vault['plan'] as $day)
                    <li class="flex gap-4 py-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-create-tint text-sm font-bold text-create-ink">{{ $day['day'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-ink">{{ $day['title'] }} <span class="font-normal text-muted">· about {{ $day['minutes'] }} minutes</span></p>
                            <p class="text-sm text-muted">{{ $day['task'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif
</div>
