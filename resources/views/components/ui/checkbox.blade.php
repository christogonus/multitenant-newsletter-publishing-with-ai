@props(['label', 'hint' => null, 'name' => null])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? 'check-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
    $message = $errorKey ? $errors->first($errorKey) : null;
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-2">
        <input
            type="checkbox"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->except('id')->class('mt-0.5 size-5 shrink-0 rounded border-line text-brand accent-brand focus:ring-2 focus:ring-brand/30') }}
        />
        <span class="text-sm text-ink">
            {{ $label }}
            @if ($hint)<span class="mt-0.5 block text-muted">{{ $hint }}</span>@endif
        </span>
    </label>
    @if ($message)
        <p class="flex items-center gap-1.5 text-sm text-danger" role="alert"><x-ui.icon name="warning" size="4" />{{ $message }}</p>
    @endif
</div>
