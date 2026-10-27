@props(['name' => null])

@php($message = $name ? $errors->first($name) : null)
@if ($message || $slot->isNotEmpty())
    <p class="validation-message" @if($name) data-error-for="{{ $name }}" @endif>{{ $message ?: $slot }}</p>
@endif
