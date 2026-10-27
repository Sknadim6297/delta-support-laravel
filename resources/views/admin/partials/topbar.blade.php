<header class="ds-header">
    <div class="ds-header-start">
        <button class="header-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="dsSidebar" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <p class="ds-header-title">@yield('page-title', 'Admin')</p>
    </div>
    <div class="ds-header-end">
        <input class="ds-search" aria-label="Search menu" data-menu-search placeholder="Search menu..." type="search">
        <div data-menu>
            <button type="button" class="ds-icon-btn" data-menu-button aria-label="Notifications" aria-haspopup="menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </button>
            <div data-menu-panel hidden role="menu">
                <p class="ds-menu-note">No new notifications</p>
            </div>
        </div>
        <div data-menu>
            <button type="button" class="ds-user-btn" data-menu-button aria-haspopup="menu" aria-expanded="false">
                <span class="ds-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                <span class="ds-user-copy">
                    <span class="ds-user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
                    <span class="ds-user-role">Administrator</span>
                </span>
                <svg class="ds-user-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div data-menu-panel hidden role="menu">
                <p class="ds-menu-note">{{ auth()->user()->email }}</p>
                <a href="{{ route('admin.change-password.index') }}" role="menuitem">Change password</a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" role="menuitem">Log out</button>
                </form>
            </div>
        </div>
    </div>
</header>
