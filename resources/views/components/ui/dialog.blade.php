@props(['title'])

<dialog {{ $attributes->merge(['class' => 'ds-modal']) }}>
    <form method="dialog">
        <h2>{{ $title }}</h2>
        <div>{{ $slot }}</div>
        <div class="ds-dialog-actions">
            @isset($actions)
                {{ $actions }}
            @else
                <x-ui.button type="submit" variant="secondary">Close</x-ui.button>
            @endisset
        </div>
    </form>
</dialog>
