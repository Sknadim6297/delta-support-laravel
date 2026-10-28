@props(['icon', 'label', 'open' => false])

<li class="lh-sb-item sb-drop-item {{ $open ? 'load-active active' : '' }}">
    <a href="javascript:void(0)" class="lh-drop-toggle">
        <i class="{{ $icon }}"></i>
        <span class="condense">{{ $label }}<i class="drop-arrow ri-arrow-down-s-line"></i></span>
    </a>
    <ul class="lh-sb-drop condense">
        {{ $slot }}
    </ul>
</li>
