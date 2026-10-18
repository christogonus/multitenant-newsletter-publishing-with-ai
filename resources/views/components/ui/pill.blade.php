@props(['tone' => 'neutral', 'pulse' => false])

<span {{ $attributes->class('inline-flex items-center gap-2 rounded-full border border-line bg-card px-2.5 py-1 text-xs font-medium text-ink') }}>
    <span class="relative flex size-2">
        @if ($pulse)
            <span @class(['absolute inline-flex size-full animate-ping rounded-full opacity-60', \App\Support\Ui::dot($tone)])></span>
        @endif
        <span @class(['relative inline-flex size-2 rounded-full', \App\Support\Ui::dot($tone)])></span>
    </span>
    {{ $slot }}
</span>
