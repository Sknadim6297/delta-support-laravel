@props([
    'title' => null,
    'subtitle' => null,
    'lead' => null,
    'flush' => false,
])

@php
    $cardSubtitle = $subtitle ?? $lead;
    $cardClass = 'ds-card'.($flush ? ' is-flush' : '');
@endphp

<section {{ $attributes->merge(['class' => $cardClass]) }}>
    @if ($title || $cardSubtitle || isset($action))
        <div class="ds-card-head">
            <div>
                @if ($title)
                    <h2>{{ $title }}</h2>
                @endif
                @if ($cardSubtitle)
                    <p>{{ $cardSubtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div class="ds-card-action">{{ $action }}</div>
            @endisset
        </div>
    @endif
    {{ $slot }}
</section>
