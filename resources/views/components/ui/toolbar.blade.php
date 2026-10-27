@props(['label' => 'Toolbar'])

<div {{ $attributes->merge(['class' => 'ds-toolbar', 'role' => 'toolbar', 'aria-label' => $label]) }}>
    {{ $slot }}
</div>
