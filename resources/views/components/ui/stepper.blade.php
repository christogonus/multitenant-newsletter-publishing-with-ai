@props(['steps' => [], 'current' => 0, 'area' => null])

{{-- $steps: list of labels, or label => done(bool) when $current is null --}}
<ol {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-2') }}>
    @foreach (array_values($steps) as $index => $label)
        @php
            $isDone = $index < $current;
            $isCurrent = $index === $current;
        @endphp
        <li class="flex items-center gap-2 sm:flex-1" @if ($isCurrent) aria-current="step" @endif>
            <span @class([
                'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                \App\Support\Ui::solid($area) => $isDone || $isCurrent,
                'border border-line bg-card text-muted' => ! $isDone && ! $isCurrent,
            ])>
                @if ($isDone)
                    <x-ui.icon name="check" size="4" />
                @else
                    {{ $index + 1 }}
                @endif
            </span>
            <span @class(['text-sm', 'font-semibold text-ink' => $isCurrent, 'text-muted' => ! $isCurrent])>{{ $label }}</span>
            @if (! $loop->last)
                <span class="hidden h-px flex-1 bg-line sm:block"></span>
            @endif
        </li>
    @endforeach
</ol>
