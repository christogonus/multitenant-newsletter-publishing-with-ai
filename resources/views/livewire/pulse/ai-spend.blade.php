<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="AI spend" details="past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse::icons.sparkles />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        @if ($calls === 0)
            <x-pulse::no-results />
        @else
            <div class="grid grid-cols-2 gap-3 text-center">
                <div>
                    <span class="block text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">${{ number_format($total, 2) }}</span>
                    <span class="text-xs font-bold text-gray-500 uppercase dark:text-gray-400">Spend</span>
                </div>
                <div>
                    <span class="block text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($calls) }}</span>
                    <span class="text-xs font-bold text-gray-500 uppercase dark:text-gray-400">AI calls</span>
                </div>
            </div>
            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">Daily alert at ${{ number_format($threshold, 2) }}</p>

            <x-pulse::table class="mt-4">
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Kind</x-pulse::th>
                        <x-pulse::th class="text-right">Calls</x-pulse::th>
                        <x-pulse::th class="text-right">Cost</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($byKind as $row)
                        <tr wire:key="kind-{{ $row->kind }}" class="h-2 first:h-0"></tr>
                        <tr wire:key="kind-{{ $row->kind }}-row">
                            <x-pulse::td>{{ $row->kind }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->calls) }}</x-pulse::td>
                            <x-pulse::td numeric class="font-bold">${{ number_format($row->cost, 2) }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>

            <x-pulse::table class="mt-4">
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Heaviest buyers</x-pulse::th>
                        <x-pulse::th class="text-right">Calls</x-pulse::th>
                        <x-pulse::th class="text-right">Cost</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($byUser as $row)
                        <tr wire:key="user-{{ $loop->index }}" class="h-2 first:h-0"></tr>
                        <tr wire:key="user-{{ $loop->index }}-row">
                            <x-pulse::td class="max-w-[1px] truncate">{{ $row->email }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->calls) }}</x-pulse::td>
                            <x-pulse::td numeric class="font-bold">${{ number_format($row->cost, 2) }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
