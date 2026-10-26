@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || $subtitle || isset($action))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-navy-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div>{{ $action }}</div>
            @endisset
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</section>
