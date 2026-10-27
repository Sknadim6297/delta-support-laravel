<header class="header-main">
    <div class="container-fluid">
        <div class="row">
            <div class="col-8 col-sm-6 d-flex align-items-center header-left p-0">
                <button class="header-toggle border-0" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="dsSidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="header-searchbar w-full">
                    <form class="mx-sm-3 app-form app-icon-form" action="#" onsubmit="return false;">
                        <div class="position-relative">
                            <input aria-label="Search menu" class="form-control" data-menu-search placeholder="Search menu..." type="search">
                            <i class="ph-bold ph-magnifying-glass"></i>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-4 col-sm-6 d-flex align-items-center justify-content-end header-right p-0">
                <div class="text-end">
                    <div class="fw-semibold">{{ $member->member_name ?? 'Member' }}</div>
                    <div class="f-s-12">{{ $member->member_id ?? session('member_context_id') }}</div>
                </div>
            </div>
        </div>
    </div>
</header>
