@props(['label', 'type' => 'button'])

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'ds-icon-btn']) }} aria-label="{{ $label }}">
    {{ $slot }}
</button>
