@props(['name'])

<div role="tabpanel" x-show="tab === @js($name)" x-cloak {{ $attributes }}>
    {{ $slot }}
</div>
