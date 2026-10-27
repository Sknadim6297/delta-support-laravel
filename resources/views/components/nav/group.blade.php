@props(['label', 'icon', 'open' => false])
@php($menuId = 'nav-'.\Illuminate\Support\Str::slug($label))
<li @class(['ds-group', 'is-open' => $open])>
    <button type="button" class="ds-menu-btn" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $menuId }}">
        <x-nav.icon :name="$icon" />
        <span>{{ $label }}</span>
        <svg class="ds-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="ds-sub" id="{{ $menuId }}" @unless($open) hidden @endunless>
        <ul>
            {{ $slot }}
        </ul>
    </div>
</li>
