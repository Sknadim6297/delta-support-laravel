@props(['href', 'active' => false, 'soon' => false, 'single' => false, 'icon' => null])
<li @class(['ds-item', 'is-active' => $active])>
    <a href="{{ $href }}" @if($active) aria-current="page" @endif>
        @if ($icon)
            <x-nav.icon :name="$icon" />
        @endif
        <span>{{ $slot }}</span>
        @if ($soon)
            <span class="ds-soon">Soon</span>
        @endif
    </a>
</li>
