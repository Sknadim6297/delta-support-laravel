@props(['title' => 'Nothing here yet', 'message' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center']) }}>
    <h3 class="text-base font-semibold text-navy-900">{{ $title }}</h3>
    @if ($message)
        <p class="mx-auto mt-2 max-w-md text-sm text-ink-500">{{ $message }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
