@props(['label', 'value', 'hint' => null])

<article {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold tracking-tight text-navy-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-2 text-sm text-ink-500">{{ $hint }}</p>
    @endif
</article>
