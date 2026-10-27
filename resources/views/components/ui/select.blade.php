@props(['label' => null, 'name'])

<label>
    @if ($label)
        <span>{{ $label }}</span>
    @endif
    <select
        class="ds-field"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        {{ $attributes->except('class') }}
    >
        {{ $slot }}
    </select>
</label>
