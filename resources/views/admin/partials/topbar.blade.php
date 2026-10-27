<header class="header-main">
    <div class="container-fluid">
        <div class="row">
            <div class="col-8 col-sm-6 d-flex align-items-center header-left p-0">
                <button class="header-toggle border-0" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="dsSidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="header-searchbar w-100">
                    <form class="mx-sm-3 app-form app-icon-form" action="#" onsubmit="return false;">
                        <div class="position-relative">
                            <input aria-label="Search menu" class="form-control" data-menu-search placeholder="Search menu..." type="search">
                            <i class="ph-bold ph-magnifying-glass"></i>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-4 col-sm-6 d-flex align-items-center justify-content-end header-right p-0">
                <div class="top-actions" data-menu>
                    <button type="button" class="d-flex align-items-center gap-2 border-0 bg-transparent" data-menu-button aria-haspopup="menu" aria-expanded="false">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-semibold" style="width:36px;height:36px;background:#0878d1;">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </span>
                        <span class="d-none d-sm-inline fw-semibold">{{ auth()->user()->name ?? 'Admin' }}</span>
                    </button>
                    <div class="ds-menu-panel" data-menu-panel hidden role="menu">
                        <p class="px-3 py-2 text-xs text-ink-500">{{ auth()->user()->email }}</p>
                        <a href="{{ route('admin.change-password.index') }}" class="block rounded-lg px-3 py-2 font-medium text-navy-900 hover:bg-brand-50" role="menuitem">Change password</a>
                        <form action="{{ route('admin.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="mt-1 w-full rounded-lg px-3 py-2 text-left font-medium text-red-700 hover:bg-red-50" role="menuitem">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
