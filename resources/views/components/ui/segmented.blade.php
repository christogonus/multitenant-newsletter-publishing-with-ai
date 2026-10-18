@props(['options' => [], 'label' => null, 'name' => null])

{{-- A radio group styled as a segmented control. Pass wire:model on the component. --}}
@php
    $model = $attributes->wire('model');
    $errorKey = $name ?? $model->value();
    $message = $errorKey ? $errors->first($errorKey) : null;
    $group = 'seg-'.\Illuminate\Support\Str::slug($errorKey ?: uniqid());
@endphp

<fieldset {{ $attributes->whereDoesntStartWith('wire:model')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <legend class="mb-1.5 text-sm font-medium text-ink">{{ $label }}</legend>
    @endif
    <div class="grid grid-cols-2 gap-1 rounded-control bg-subtle p-1 sm:flex">
        @foreach ($options as $value => $optionLabel)
            <label class="flex-1 cursor-pointer">
                <input type="radio" name="{{ $group }}" value="{{ $value }}" {{ $model }} class="peer sr-only" />
                <span class="flex min-h-11 items-center justify-center rounded-[6px] px-3 text-center text-sm font-semibold text-muted transition peer-checked:bg-card peer-checked:text-ink peer-checked:shadow-card peer-focus-visible:outline-2 peer-focus-visible:outline-brand hover:text-ink">
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>
    @if ($message)
        <p class="flex items-center gap-1.5 text-sm text-danger" role="alert"><x-ui.icon name="warning" size="4" />{{ $message }}</p>
    @endif
</fieldset>
