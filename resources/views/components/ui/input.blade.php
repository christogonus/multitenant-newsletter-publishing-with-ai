@props(['label' => null, 'hint' => null, 'name' => null, 'type' => 'text', 'optional' => false, 'icon' => null])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? 'field-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
    $message = $errorKey ? $errors->first($errorKey) : null;
@endphp

<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :optional="$optional">
    <div class="relative">
        @if ($icon)
            <x-ui.icon :name="$icon" size="5" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
        @endif
        <input
            type="{{ $type }}"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($message) aria-invalid="true" @endif
            {{ $attributes->except('id')->class([
                'block min-h-11 w-full rounded-control border bg-card px-3 text-base text-ink placeholder:text-muted/70 shadow-sm transition sm:text-sm',
                'focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none',
                'border-danger' => $message,
                'border-line' => ! $message,
                'pl-10' => $icon,
            ]) }}
        />
    </div>
</x-ui.field>
