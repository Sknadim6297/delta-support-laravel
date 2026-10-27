@props(['label' => 'Search'])

<input {{ $attributes->merge(['class' => 'ds-search', 'type' => 'search', 'aria-label' => $label]) }}>
