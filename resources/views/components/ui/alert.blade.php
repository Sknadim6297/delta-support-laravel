@props(['type' => 'info'])

@php
    $classes = match ($type) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-brand-100 bg-brand-50 text-brand-700',
    };
@endphp

<div role="status" {{ $attributes->merge(['class' => 'mb-4 rounded-xl border px-4 py-3 text-sm '.$classes]) }}>
    {{ $slot }}
</div>
