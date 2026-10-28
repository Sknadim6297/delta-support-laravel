@php
    $pageTitle = $luxTitle ?? ($luxFallback ?? 'Panel');
    $initial = strtoupper(substr((string) ($luxName ?? 'U'), 0, 1));
@endphp
<header class="lh-header ds-lux-bar">
    <div class="ds-lux-bar-inner">
        <div class="ds-lux-bar-start">
            <button type="button" class="lh-toggle-sidebar" aria-label="Toggle navigation">
                <i class="ri-menu-line"></i>
            </button>
            <p class="ds-lux-title">{{ $pageTitle }}</p>
        </div>
        <div class="ds-lux-bar-end">
            <div class="ds-lux-menu" data-lux-drop>
                <button type="button" class="ds-lux-icon-btn" data-lux-drop-button aria-label="Notifications" aria-expanded="false">
                    <i class="ri-notification-2-line"></i>
                </button>
                <div class="ds-lux-menu-panel" role="menu">
                    <p>No new notifications</p>
                </div>
            </div>
            <div class="ds-lux-menu" data-lux-drop>
                <button type="button" class="ds-lux-user-btn" data-lux-drop-button aria-label="Account menu" aria-expanded="false">
                    <span class="ds-lux-avatar">{{ $initial }}</span>
                    <span class="ds-lux-user-copy">
                        <strong>{{ $luxName }}</strong>
                        @if (!empty($luxMeta))
                            <small>{{ $luxMeta }}</small>
                        @endif
                    </span>
                </button>
                <div class="ds-lux-menu-panel" role="menu">
                    @if (!empty($luxProfile))
                        <a href="{{ $luxProfile }}">{{ $luxProfileLabel ?? 'Profile' }}</a>
                    @endif
                    @if (!empty($luxPassword))
                        <a href="{{ $luxPassword }}">Change password</a>
                    @endif
                    <form action="{{ $luxLogout }}" method="POST">
                        @csrf
                        <button type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
