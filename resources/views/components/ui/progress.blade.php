@props(['value' => 0, 'max' => 100, 'area' => null, 'label' => null])

@php
    $percent = $max > 0 ? min(100, max(0, round($value / $max * 100))) : 0;
@endphp

<div {{ $attributes->class('w-full') }}>
    @if ($label)
        <div class="mb-1.5 flex justify-between text-sm">
            <span class="font-medium text-ink">{{ $label }}</span>
            <span class="text-muted tabular-nums">{{ $percent }}%</span>
        </div>
    @endif
    <div class="h-2 w-full overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" @if ($label) aria-label="{{ $label }}" @endif>
        <div @class(['h-full rounded-full transition-all duration-500', \App\Support\Ui::bar($area)]) style="width: {{ $percent }}%"></div>
    </div>
</div>
