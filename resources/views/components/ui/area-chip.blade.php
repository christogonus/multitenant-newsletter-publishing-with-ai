@props(['area' => null, 'icon', 'size' => 'md'])

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-full shadow-card ring-4 ring-white',
    \App\Support\Ui::chip($area),
    'size-11' => $size === 'md',
    'size-8' => $size === 'sm',
    'size-14' => $size === 'lg',
]) }}>
    <x-ui.icon :name="$icon" :size="$size === 'lg' ? 6 : 5" />
</span>
