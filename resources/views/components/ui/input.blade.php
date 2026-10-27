@props(['label' => null, 'name', 'type' => 'text', 'error' => null])

<label class="ds-field-label">
    @if ($label)
        <span>{{ $label }}</span>
    @endif
    <input
        class="ds-field"
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class') }}
    >
</label>
@if ($error)
    <p class="validation-message">{{ $error }}</p>
@endif
