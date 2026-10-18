<div class="space-y-6">
    <x-ui.page-header title="Reports" description="Pages readers flagged. Take a page offline if it breaks the terms." icon="flag">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.spend')">AI spend</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.checkbox wire:model.live="showResolved" label="Show resolved reports" />

    <x-ui.table :headers="['Publication', 'Reason', 'Details', 'When', '']" :empty="$reports->isEmpty()">
        <x-slot:emptyState><x-ui.empty-state icon="check-circle" area="grow" title="No open reports" /></x-slot:emptyState>
        @foreach ($reports as $report)
            <tr wire:key="report-{{ $report->id }}">
                <td class="px-4 py-3">
                    <a href="{{ $report->newsletter->publicUrl($report->issue?->public_slug ? 'issues/'.$report->issue->public_slug : '') }}" target="_blank" rel="noopener" class="font-medium text-brand hover:underline">{{ $report->newsletter->name }}</a>
                    @if ($report->issue)<p class="text-xs text-muted">{{ $report->issue->title }}</p>@endif
                </td>
                <td class="px-4 py-3"><x-ui.badge tone="danger">{{ ucfirst($report->reason) }}</x-ui.badge></td>
                <td class="max-w-xs px-4 py-3 text-muted">{{ $report->details ?: '—' }}<p class="text-xs">{{ $report->reporter_email }}</p></td>
                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $report->created_at->diffForHumans() }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($report->newsletter->moderated_at)
                        <x-ui.button variant="ghost" size="sm" wire:click="restore({{ $report->newsletter_id }})">Put back online</x-ui.button>
                    @else
                        <x-ui.button variant="danger" size="sm" wire:click="takeOffline({{ $report->newsletter_id }})" wire:confirm="Take {{ $report->newsletter->name }} offline?">Take offline</x-ui.button>
                    @endif
                    @unless ($report->resolved_at)
                        <x-ui.button variant="secondary" size="sm" wire:click="resolve({{ $report->id }})">Resolve</x-ui.button>
                    @endunless
                </td>
            </tr>
        @endforeach
        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    @if ($offline->isNotEmpty())
        <x-ui.card title="Offline publications">
            <ul class="divide-y divide-line text-sm">
                @foreach ($offline as $newsletter)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-ink">{{ $newsletter->name }} <span class="text-muted">since {{ $newsletter->moderated_at->toFormattedDateString() }}</span></span>
                        <x-ui.button variant="ghost" size="sm" wire:click="restore({{ $newsletter->id }})">Put back online</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
