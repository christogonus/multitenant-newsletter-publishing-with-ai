<div class="space-y-6">
    <x-ui.page-header title="AI spend" description="What AI writing costs, and who uses the most." icon="chart-bar">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('admin.users')">Buyers</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.reports')">Reports</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.content')">Content</x-ui.button>
            <x-ui.button variant="ghost" href="/pulse" icon-right="external">Pulse</x-ui.button>
            <x-ui.button variant="ghost" href="/horizon" icon-right="external">Queues</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($threshold > 0 && $today >= $threshold)
        <x-ui.alert tone="danger" title="Above the daily alert level">Spend in the last 24 hours is ${{ number_format($today, 2) }}, above the ${{ number_format($threshold, 2) }} alert level.</x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Last 24 hours" :value="'$'.number_format($today, 2)" :hint="'Alert at $'.number_format($threshold, 2)" icon="clock" />
        <x-ui.stat-card label="Last 7 days" :value="'$'.number_format($week, 2)" icon="calendar" />
        <x-ui.stat-card label="Last 30 days" :value="'$'.number_format($month, 2)" icon="chart-bar" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.table :headers="['Kind (30 days)', 'Calls', 'Tokens', 'Cost']" :empty="$byKind->isEmpty()">
            <x-slot:emptyState><x-ui.empty-state icon="chart-bar" title="No AI usage yet" /></x-slot:emptyState>
            @foreach ($byKind as $row)
                <tr>
                    <td class="px-4 py-3 text-ink">{{ $row->kind->value }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ number_format($row->calls) }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ number_format($row->tokens) }}</td>
                    <td class="px-4 py-3 text-ink tabular-nums">${{ number_format($row->total, 2) }}</td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.table :headers="['Heaviest users (7 days)', 'Calls', 'Cost']" :empty="$topUsers->isEmpty()">
            <x-slot:emptyState><x-ui.empty-state icon="users" title="No AI usage yet" /></x-slot:emptyState>
            @foreach ($topUsers as $row)
                <tr>
                    <td class="px-4 py-3 text-ink">{{ $row->user?->email ?? 'Deleted user' }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ number_format($row->calls) }}</td>
                    <td class="px-4 py-3 text-ink tabular-nums">${{ number_format($row->total, 2) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>
</div>
