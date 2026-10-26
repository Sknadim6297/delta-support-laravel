@props(['title'])

<dialog {{ $attributes->merge(['class' => 'w-full max-w-md rounded-2xl border border-slate-200 p-0 text-navy-900 shadow-xl backdrop:bg-navy-900/40']) }}>
    <form method="dialog" class="p-5">
        <h2 class="text-lg font-semibold">{{ $title }}</h2>
        <div class="mt-2 text-sm text-ink-500">{{ $slot }}</div>
        <div class="mt-5 flex justify-end gap-2">
            @isset($actions)
                {{ $actions }}
            @else
                <x-ui.button type="submit" variant="secondary">Close</x-ui.button>
            @endisset
        </div>
    </form>
</dialog>
