<header class="top-navbar">
    <button class="menu-toggle" type="button" id="sidebarToggle" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
    </button>

    <div class="top-actions" data-menu>
        <button type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 text-left" data-menu-button aria-haspopup="menu" aria-expanded="false">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-xs font-semibold text-white">
                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
            </span>
            <span class="hidden text-sm font-semibold text-navy-900 sm:inline">{{ auth()->user()->name ?? 'Admin' }}</span>
            <i class="bi bi-chevron-down text-xs text-ink-500"></i>
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
</header>
