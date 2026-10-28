@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => null,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'ds-btn ds-btn-secondary',
        'danger' => 'ds-btn ds-btn-danger',
        'success' => 'ds-btn ds-btn-success',
        'outline' => 'ds-btn ds-btn-outline',
        default => 'ds-btn',
    };
    if ($size === 'sm') {
        $variantClass .= ' ds-btn-sm';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $variantClass]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $variantClass]) }}>{{ $slot }}</button>
@endif
