@props(['label', 'value', 'hint' => null])

<article {{ $attributes->merge(['class' => 'ds-stat']) }}>
    @isset($icon)
        <div class="ds-stat-top">
            <span class="ds-stat-icon" aria-hidden="true">{{ $icon }}</span>
            <p class="ds-stat-label">{{ $label }}</p>
        </div>
    @else
        <p class="ds-stat-label">{{ $label }}</p>
    @endisset
    <p class="ds-stat-value">{{ $value }}</p>
    @if ($hint)
        <p class="ds-stat-hint">{{ $hint }}</p>
    @endif
</article>
