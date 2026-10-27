@props(['tone' => 'brand'])

@php
    $toneClass = match ($tone) {
        'success', 'active' => 'ds-badge ds-badge-success',
        'warning', 'inactive' => 'ds-badge ds-badge-warning',
        'muted' => 'ds-badge ds-badge-muted',
        'danger' => 'ds-badge ds-badge-danger',
        default => 'ds-badge',
    };
@endphp

<span {{ $attributes->merge(['class' => $toneClass]) }}>{{ $slot }}</span>
