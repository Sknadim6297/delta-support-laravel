@props(['tone' => 'brand'])

@php
    $classes = match ($tone) {
        'muted' => 'bg-slate-100 text-slate-600',
        'warning' => 'bg-amber-50 text-amber-700',
        'success' => 'bg-emerald-50 text-emerald-700',
        default => 'bg-brand-50 text-brand-700',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.$classes]) }}>
    {{ $slot }}
</span>
