@props(['label' => null, 'hint' => null, 'name' => null, 'options' => [], 'placeholder' => null, 'optional' => false])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? 'field-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
    $message = $errorKey ? $errors->first($errorKey) : null;
@endphp

<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :optional="$optional">
    <div class="relative">
        <select
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->except('id')->class([
                'block min-h-11 w-full appearance-none rounded-control border bg-card pr-10 pl-3 text-base text-ink shadow-sm sm:text-sm',
                'focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none',
                'border-danger' => $message,
                'border-line' => ! $message,
            ]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}">{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-down" size="4" class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted" />
    </div>
</x-ui.field>
