@props(['label' => null, 'name', 'error' => null])

<label class="ds-field-label">
    @if ($label)
        <span>{{ $label }}</span>
    @endif
    <textarea
        class="ds-field"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class') }}
    >{{ $slot }}</textarea>
</label>
@if ($error)
    <p class="validation-message">{{ $error }}</p>
@endif
