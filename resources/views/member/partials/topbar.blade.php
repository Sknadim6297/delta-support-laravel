<header class="top-navbar">
    <button class="menu-toggle" type="button" id="sidebarToggle" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
    </button>
    <div class="text-sm font-semibold text-navy-900">{{ $member->member_name ?? 'Member' }}</div>
    <form action="{{ route('member.logout') }}" method="POST">
        @csrf
        <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-semibold text-white">Log out</button>
    </form>
</header>
