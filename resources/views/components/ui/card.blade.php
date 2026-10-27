@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'ds-card']) }}>
    @if ($title || $subtitle || isset($action))
        <div class="ds-card-head">
            <div>
                @if ($title)
                    <h2>{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div class="ds-card-action">{{ $action }}</div>
            @endisset
        </div>
    @endif
    {{ $slot }}
</section>
