@props(['tone' => 'neutral', 'icon' => null])

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', \App\Support\Ui::tint($tone)]) }}>
    @if ($icon)<x-ui.icon :name="$icon" size="3" />@endif
    {{ $slot }}
</span>
