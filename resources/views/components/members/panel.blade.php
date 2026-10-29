@props(['title' => null, 'subtitle' => null, 'flush' => false])

<section {{ $attributes->merge(['class' => 'ds-mm-panel']) }}>
    @if ($title || $subtitle || isset($tools))
        <div class="ds-mm-panel-head">
            <div>
                @if ($title)
                    <h2>{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @isset($tools)
                <div class="ds-mm-tools">{{ $tools }}</div>
            @endisset
        </div>
    @endif
    <div class="ds-mm-panel-body {{ $flush ? 'is-flush' : '' }}">
        {{ $slot }}
    </div>
</section>
