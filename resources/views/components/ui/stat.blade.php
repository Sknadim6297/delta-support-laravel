@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => null,
])

@php
    $iconClass = 'pv-stat-icon';
    if ($tone) {
        $iconClass .= ' is-'.preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $tone));
    }
@endphp

<article {{ $attributes->merge(['class' => 'ds-stat']) }}>
    @if ($icon)
        <span class="{{ $iconClass }}" aria-hidden="true"><i class="{{ $icon }}"></i></span>
    @endif
    <p class="ds-stat-label">{{ $label }}</p>
    <p class="ds-stat-value">{{ $value }}</p>
    @if ($hint)
        <p class="ds-stat-hint">{{ $hint }}</p>
    @endif
</article>
