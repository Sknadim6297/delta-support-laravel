@props(['href', 'active' => false])

<li>
    <a href="{{ $href }}" @class(['is-active' => $active])>{{ $slot }}</a>
</li>
