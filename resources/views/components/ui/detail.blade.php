@props(['label'])

<div {{ $attributes->merge(['class' => 'ds-detail-item']) }}>
    <p class="ds-stat-label">{{ $label }}</p>
    <p class="ds-stat-value">{{ $slot }}</p>
</div>
