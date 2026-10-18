@props(['headers' => [], 'empty' => false])

<div {{ $attributes->class('overflow-hidden rounded-card border border-line bg-card shadow-card') }}>
    @if ($empty)
        {{ $emptyState ?? '' }}
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                @if ($headers)
                    <thead class="bg-subtle/60">
                        <tr>
                            @foreach ($headers as $header)
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-muted uppercase">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody class="divide-y divide-line">
                    {{ $slot }}
                </tbody>
            </table>
        </div>
    @endif
    @isset($footer)
        <div class="border-t border-line px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
