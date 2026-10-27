@props(['title', 'lead' => null])

<div {{ $attributes->merge(['class' => 'ds-section-head']) }}>
    <h2>{{ $title }}</h2>
    @if ($lead)
        <p>{{ $lead }}</p>
    @endif
</div>
