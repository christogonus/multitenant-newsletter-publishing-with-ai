@props(['label' => null, 'hint' => null, 'name' => null, 'rows' => 4, 'optional' => false])

@php
    $errorKey = $name ?? $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? 'field-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
    $message = $errorKey ? $errors->first($errorKey) : null;
    $maxLength = $attributes->get('maxlength');
@endphp

<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :optional="$optional" :x-data="$maxLength ? '{ length: 0 }' : null">
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($message) aria-invalid="true" @endif
        @if ($maxLength) x-init="$nextTick(() => length = $el.value.length)" x-on:input="length = $el.value.length" @endif
        {{ $attributes->except('id')->class([
            'block w-full rounded-control border bg-card px-3 py-2.5 text-base text-ink placeholder:text-muted/70 shadow-sm transition sm:text-sm',
            'focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none',
            'border-danger' => $message,
            'border-line' => ! $message,
        ]) }}
    >{{ $slot }}</textarea>

    @if ($maxLength)
        <p wire:ignore class="text-right text-xs tabular-nums" x-bind:class="length >= {{ (int) $maxLength }} ? 'text-danger' : 'text-muted'">
            <span x-text="length">0</span> / {{ $maxLength }}
        </p>
    @endif
</x-ui.field>
