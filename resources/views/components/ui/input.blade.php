@props(['label' => null, 'name', 'type' => 'text', 'error' => null])

<label class="block text-sm font-medium text-navy-900">
    @if ($label)
        <span>{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-navy-900 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10']) }}
    >
</label>
@if ($error)
    <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
@endif
