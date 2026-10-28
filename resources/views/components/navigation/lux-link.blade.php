@props(['href', 'active' => false])

<li class="list">
    <a href="{{ $href }}" @class(['lh-page-link drop', 'active-link' => $active])>
        <i class="ri-git-commit-line"></i>{{ $slot }}
    </a>
</li>
