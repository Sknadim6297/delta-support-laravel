@props(['icon', 'label', 'open' => false])

<li class="pv-nav-item {{ $open ? 'is-open' : '' }}" data-pv-nav-item>
    <button
        type="button"
        class="pv-nav-toggle"
        data-pv-nav-toggle
        aria-expanded="{{ $open ? 'true' : 'false' }}"
    >
        <i class="{{ $icon }}" aria-hidden="true"></i>
        <span class="pv-nav-label">{{ $label }}</span>
        <i class="ri-arrow-down-s-line pv-nav-caret" aria-hidden="true"></i>
    </button>
    <ul class="pv-nav-sub">
        {{ $slot }}
    </ul>
</li>
