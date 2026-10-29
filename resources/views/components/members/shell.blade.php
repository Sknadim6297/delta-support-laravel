@props(['kicker' => 'Member management', 'lead' => null])

<div {{ $attributes->merge(['class' => 'ds-mm']) }}>
    <header class="ds-mm-head">
        <div class="ds-mm-head-copy">
            <p class="ds-mm-kicker">{{ $kicker }}</p>
            @if (filled($lead))
                <p class="ds-mm-lead">{{ $lead }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="ds-mm-head-actions">{{ $actions }}</div>
        @endisset
    </header>
    {{ $slot }}
</div>
