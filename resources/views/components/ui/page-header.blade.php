@props(['title', 'kicker' => null, 'lead' => null])

<header {{ $attributes->merge(['class' => 'ds-page-head']) }}>
    <div class="ds-page-head-copy">
        @isset($breadcrumb)
            {{ $breadcrumb }}
        @endisset
        @if ($kicker)
            <p class="ds-kicker">{{ $kicker }}</p>
        @endif
        <h1>{{ $title }}</h1>
        @if ($lead)
            <p class="ds-lead">{{ $lead }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="ds-actions">{{ $actions }}</div>
    @endisset
</header>
