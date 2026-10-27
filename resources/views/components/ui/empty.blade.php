@props(['title' => 'Nothing here yet', 'message' => null])

<div {{ $attributes->merge(['class' => 'ds-empty']) }}>
    @isset($icon)
        <div class="ds-empty-icon">{{ $icon }}</div>
    @endisset
    <h3>{{ $title }}</h3>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="ds-empty-actions">{{ $slot }}</div>
    @endif
</div>
