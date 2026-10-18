@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'optional' => false])

<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-sm font-medium text-ink">
            {{ $label }}
            @if ($optional)<span class="font-normal text-muted">(optional)</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="flex items-center gap-1.5 text-sm text-danger" role="alert">
            <x-ui.icon name="warning" size="4" />
            {{ $error }}
        </p>
    @elseif ($hint)
        <p class="text-sm text-muted">{{ $hint }}</p>
    @endif
</div>
