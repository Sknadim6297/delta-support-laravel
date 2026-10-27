@props(['label', 'name'])

<label class="ds-check">
    <input type="checkbox" name="{{ $name }}" {{ $attributes }}>
    <span>{{ $label }}</span>
</label>
