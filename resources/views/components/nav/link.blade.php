@props(['href', 'active' => false, 'soon' => false])
<a href="{{ $href }}" @class(['is-active' => $active])>
    <span>{{ $slot }}</span>
    @if($soon)<span class="ds-soon">Soon</span>@endif
</a>
