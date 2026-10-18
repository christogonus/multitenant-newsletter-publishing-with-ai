<div class="space-y-6" @if ($hasGenerating) wire:poll.4s @endif>
    <x-ui.page-header title="Issues" description="Everything you have written, in one place." icon="newspaper" area="create">
        <x-slot:actions>
            <x-ui.button :href="route('issues.create')" area="create" icon="sparkles" size="lg">Write a new issue</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div role="tablist" class="flex gap-1 overflow-x-auto rounded-control bg-subtle p-1 sm:w-fit">
        @foreach ($filters as $key => $label)
            <button
                type="button"
                role="tab"
                wire:click="$set('status', '{{ $key }}')"
                aria-selected="{{ $status === $key ? 'true' : 'false' }}"
                @class([
                    'min-h-11 rounded-[6px] px-4 text-sm font-semibold whitespace-nowrap transition sm:min-h-9',
                    'bg-card text-ink shadow-card' => $status === $key,
                    'text-muted hover:text-ink' => $status !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <x-ui.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Search your issues" aria-label="Search issues" />

    <x-ui.table :headers="['Issue', 'Status', 'Views', 'Updated', '']" :empty="$issues->isEmpty()">
        <x-slot:emptyState>
            <x-ui.empty-state icon="newspaper" area="create" title="Nothing here yet" description="Write an issue and it shows up here.">
                <x-ui.button :href="route('issues.create')" area="create" icon="sparkles">Write a new issue</x-ui.button>
            </x-ui.empty-state>
        </x-slot:emptyState>

        @foreach ($issues as $issue)
            <tr wire:key="issue-{{ $issue->id }}" class="hover:bg-subtle/50">
                <td class="max-w-md px-4 py-3">
                    <a href="{{ route('issues.edit', $issue) }}" class="block truncate font-medium text-ink hover:text-brand">{{ $issue->title }}</a>
                    @if ($issue->subject_line)<p class="truncate text-xs text-muted">{{ $issue->subject_line }}</p>@endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <x-ui.pill :tone="$issue->status->tone()" :pulse="$issue->status === \App\Enums\IssueStatus::Generating">{{ $issue->status->label() }}</x-ui.pill>
                </td>
                <td class="px-4 py-3 text-muted tabular-nums">{{ $issue->isPublished() ? number_format($issue->views) : '—' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $issue->updated_at->diffForHumans() }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <x-ui.button variant="secondary" size="sm" :href="route('issues.edit', $issue)">Open</x-ui.button>
                </td>
            </tr>
        @endforeach

        @if ($issues->hasPages())
            <x-slot:footer>{{ $issues->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
