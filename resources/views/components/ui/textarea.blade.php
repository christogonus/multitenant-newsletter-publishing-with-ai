@props(['label' => null, 'hint' => null, 'name' => null, 'rows' => 4, 'optional' => false])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? 'field-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
    $message = $errorKey ? $errors->first($errorKey) : null;
@endphp

<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :optional="$optional">
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($message) aria-invalid="true" @endif
        {{ $attributes->except('id')->class([
            'block w-full rounded-control border bg-card px-3 py-2.5 text-base text-ink placeholder:text-muted/70 shadow-sm transition sm:text-sm',
            'focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none',
            'border-danger' => $message,
            'border-line' => ! $message,
        ]) }}
    >{{ $slot }}</textarea>
</x-ui.field>
