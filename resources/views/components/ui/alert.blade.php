@props(['type' => 'info'])

@php
    $alertClass = match ($type) {
        'error' => 'ds-alert ds-alert-error',
        'warning' => 'ds-alert ds-alert-warning',
        default => 'ds-alert',
    };
@endphp

<div role="status" {{ $attributes->merge(['class' => $alertClass]) }}>
    {{ $slot }}
</div>
