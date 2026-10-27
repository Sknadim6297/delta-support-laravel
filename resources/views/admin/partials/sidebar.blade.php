<aside id="desktopSidebar" class="sidebar desktop-sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('site/img/logo.png') }}" class="sidebar-logo-image" alt="Delta Support">
    </div>

    <nav class="sidebar-menu ds-nav" aria-label="Admin">
        <x-nav.link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav.link>

        <x-nav.group label="Member Management" icon="bi-people" :open="request()->routeIs('admin.members.*', 'admin.genealogy.tree-view')">
            <x-nav.link :href="route('admin.members.directory')" :active="request()->routeIs('admin.members.directory')">All Members</x-nav.link>
            <x-nav.link :href="route('admin.members.update')" :active="request()->routeIs('admin.members.update')">Member Details</x-nav.link>
            <x-nav.link :href="route('admin.genealogy.tree-view')" :active="request()->routeIs('admin.genealogy.tree-view')">Referral Tree</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Package Management" icon="bi-box-seam" :open="request()->routeIs('admin.packages.*', 'admin.purchases.*')">
            <x-nav.link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.index')">All Packages</x-nav.link>
            <x-nav.link :href="route('admin.packages.create')" :active="request()->routeIs('admin.packages.create')">Add Package</x-nav.link>
            <x-nav.link :href="route('admin.purchases.index')" :active="request()->routeIs('admin.purchases.*')">Package Purchases</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Payment Management" icon="bi-qr-code" :open="request()->routeIs('admin.payments.*')">
            <x-nav.link :href="route('admin.payments.index', ['status' => 'pending'])" :active="request()->routeIs('admin.payments.index') && request()->query('status', 'pending') === 'pending'">Pending Payments</x-nav.link>
            <x-nav.link :href="route('admin.payments.index', ['status' => 'approved'])" :active="request()->query('status') === 'approved'">Approved Payments</x-nav.link>
            <x-nav.link :href="route('admin.payments.index', ['status' => 'rejected'])" :active="request()->query('status') === 'rejected'">Rejected Payments</x-nav.link>
            <x-nav.link :href="route('admin.payments.settings')" :active="request()->routeIs('admin.payments.settings', 'admin.payments.qr')">Payment Settings</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Invoices" icon="bi-receipt" :open="request()->routeIs('admin.invoices.*')">
            <x-nav.link :href="route('admin.invoices.index')" :active="request()->routeIs('admin.invoices.*')">All Invoices</x-nav.link>
        </x-nav.group>

        <x-nav.group label="ROI Management" icon="bi-calendar2-check">
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'roi-plans'])" soon>ROI Plans</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'roi-schedule'])" soon>ROI Schedule</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'roi-history'])" soon>ROI History</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Level Income" icon="bi-diagram-3">
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'commission-settings'])" soon>Commission Settings</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'level-unlock-rules'])" soon>Level Unlock Rules</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'income-history'])" soon>Income History</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Wallet Management" icon="bi-wallet2" :open="request()->routeIs('admin.wallets.*')">
            <x-nav.link :href="route('admin.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-nav.link>
            <x-nav.link :href="route('admin.wallets.show', ['wallet' => 'working'])" :active="request()->route('wallet') === 'working'">Working Wallet</x-nav.link>
            <x-nav.link :href="route('admin.wallets.transactions')" :active="request()->routeIs('admin.wallets.transactions')">Wallet Transactions</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Withdrawals" icon="bi-cash-coin">
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'withdrawal-requests'])" soon>Withdrawal Requests</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'withdrawal-charges'])" soon>Withdrawal Charges</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'payout-history'])" soon>Payout History</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Tour Management" icon="bi-airplane" :open="request()->routeIs('admin.tours.*')">
            <x-nav.link :href="route('admin.tours.index')" :active="request()->routeIs('admin.tours.index')">Tour Packages</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'booking-requests'])" soon>Booking Requests</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Rewards" icon="bi-award">
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'reward-plans'])" soon>Reward Plans</x-nav.link>
            <x-nav.link :href="route('admin.coming-soon', ['feature' => 'reward-achievements'])" soon>Achievements</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Reports" icon="bi-bar-chart" :open="request()->routeIs('admin.catalog-reports.*')">
            <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'members'])">Member Reports</x-nav.link>
            <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'packages'])">Package Reports</x-nav.link>
            <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'payments'])">Payment Reports</x-nav.link>
            <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'earnings'])" soon>Earnings Reports</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Settings" icon="bi-gear" :open="request()->routeIs('admin.settings.*', 'admin.change-password.*')">
            <x-nav.link :href="route('admin.settings.general')" :active="request()->routeIs('admin.settings.general')">General Settings</x-nav.link>
            <x-nav.link :href="route('admin.settings.branding')" :active="request()->routeIs('admin.settings.branding')">Branding</x-nav.link>
            <x-nav.link :href="route('admin.settings.security')" :active="request()->routeIs('admin.settings.security', 'admin.change-password.*')">Security</x-nav.link>
            <x-nav.link :href="route('admin.settings.profile')" :active="request()->routeIs('admin.settings.profile')">Admin Profile</x-nav.link>
        </x-nav.group>

        <form action="{{ route('admin.logout') }}" method="POST">
            @csrf
            <button type="submit"><i class="bi bi-box-arrow-right"></i><span>Log out</span></button>
        </form>
    </nav>
</aside>
