@props(['label', 'icon', 'open' => false])
<details class="ds-nav-group" @if($open) open @endif>
    <summary><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></summary>
    <div class="ds-nav-links">{{ $slot }}</div>
</details>
