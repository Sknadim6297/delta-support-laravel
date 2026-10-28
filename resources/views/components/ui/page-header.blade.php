@props(['title' => null, 'kicker' => null, 'lead' => null, 'heading' => false])

@php
    $showHeading = $heading && filled($title);
    $hasLead = filled($lead);
    $hasActions = isset($actions) && ! $actions->isEmpty();
    $hasBreadcrumb = isset($breadcrumb) && ! $breadcrumb->isEmpty();
@endphp

@if ($showHeading || $hasLead || $hasActions || $hasBreadcrumb)
<header {{ $attributes->merge(['class' => 'ds-page-head']) }}>
    <div class="ds-page-head-copy">
        @if ($hasBreadcrumb)
            {{ $breadcrumb }}
        @endif
        @if ($showHeading)
            @if ($kicker)
                <p class="ds-kicker">{{ $kicker }}</p>
            @endif
            <h2>{{ $title }}</h2>
        @endif
        @if ($hasLead)
            <p class="ds-lead">{{ $lead }}</p>
        @endif
    </div>
    @if ($hasActions)
        <div class="ds-actions">{{ $actions }}</div>
    @endif
</header>
@endif
