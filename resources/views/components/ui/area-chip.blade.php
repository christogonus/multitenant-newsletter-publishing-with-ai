@props(['area' => null, 'icon', 'size' => 'md'])

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-control',
    \App\Support\Ui::tint($area),
    'size-10' => $size === 'md',
    'size-8' => $size === 'sm',
    'size-12' => $size === 'lg',
]) }}>
    <x-ui.icon :name="$icon" :size="$size === 'lg' ? 6 : 5" />
</span>
