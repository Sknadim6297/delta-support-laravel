<aside id="dsSidebar" aria-label="Admin">
    <div class="ds-brand">
        <a href="{{ route('dashboard') }}">
            <img class="ds-logo" src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support">
        </a>
        <button type="button" class="ds-sidebar-close" aria-label="Close menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
    <div class="ds-sidebar-scroll">
        <ul class="ds-menu">
            <x-nav.link single icon="layout-dashboard" :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav.link>

            <x-nav.group label="Member Management" icon="users" :open="request()->routeIs('admin.members.*', 'admin.genealogy.tree-view')">
                <x-nav.link icon="users" :href="route('admin.members.directory')" :active="request()->routeIs('admin.members.directory')">All Members</x-nav.link>
                <x-nav.link icon="user-plus" :href="route('admin.members.registration')" :active="request()->routeIs('admin.members.registration')">Member Registration</x-nav.link>
                <x-nav.link icon="user-round-pen" :href="route('admin.members.update')" :active="request()->routeIs('admin.members.update')">View / Update Member Profile</x-nav.link>
                <x-nav.link icon="user-round-x" :href="route('admin.members.inactive')" :active="request()->routeIs('admin.members.inactive')">Inactive Member List</x-nav.link>
                <x-nav.link icon="user-round-check" :href="route('admin.members.active')" :active="request()->routeIs('admin.members.active')">Active Member List</x-nav.link>
                <x-nav.link icon="network" :href="route('admin.genealogy.tree-view')" :active="request()->routeIs('admin.genealogy.tree-view')">Referral Tree</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Package Management" icon="package" :open="request()->routeIs('admin.packages.*', 'admin.purchases.*')">
                <x-nav.link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.index')">All Packages</x-nav.link>
                <x-nav.link :href="route('admin.packages.create')" :active="request()->routeIs('admin.packages.create')">Add Package</x-nav.link>
                <x-nav.link :href="route('admin.purchases.index')" :active="request()->routeIs('admin.purchases.*')">Package Purchases</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Payment Management" icon="credit-card" :open="request()->routeIs('admin.payments.*')">
                <x-nav.link :href="route('admin.payments.index', ['status' => 'pending'])" :active="request()->routeIs('admin.payments.index') && request()->query('status', 'pending') === 'pending'">Pending Payments</x-nav.link>
                <x-nav.link :href="route('admin.payments.index', ['status' => 'approved'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'approved'">Approved Payments</x-nav.link>
                <x-nav.link :href="route('admin.payments.index', ['status' => 'rejected'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'rejected'">Rejected Payments</x-nav.link>
                <x-nav.link :href="route('admin.payments.settings')" :active="request()->routeIs('admin.payments.settings', 'admin.payments.qr')">Payment Settings</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Invoices" icon="receipt" :open="request()->routeIs('admin.invoices.*')">
                <x-nav.link :href="route('admin.invoices.index')" :active="request()->routeIs('admin.invoices.*')">All Invoices</x-nav.link>
            </x-nav.group>

            <x-nav.group label="ROI Management" icon="trending-up" :open="request()->routeIs('admin.finance.roi-history', 'admin.finance.roi-plans', 'admin.finance.roi-schedule')">
                <x-nav.link :href="route('admin.finance.roi-plans')" :active="request()->routeIs('admin.finance.roi-plans')">ROI Plans</x-nav.link>
                <x-nav.link :href="route('admin.finance.roi-schedule')" :active="request()->routeIs('admin.finance.roi-schedule')">ROI Schedule</x-nav.link>
                <x-nav.link :href="route('admin.finance.roi-history')" :active="request()->routeIs('admin.finance.roi-history')">ROI History</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Level Income" icon="network" :open="request()->routeIs('admin.finance.level-income', 'admin.finance.eligibility', 'admin.finance.commission-settings', 'admin.finance.level-unlock-rules')">
                <x-nav.link :href="route('admin.finance.commission-settings')" :active="request()->routeIs('admin.finance.commission-settings')">Commission Settings</x-nav.link>
                <x-nav.link :href="route('admin.finance.level-unlock-rules')" :active="request()->routeIs('admin.finance.level-unlock-rules')">Level Unlock Rules</x-nav.link>
                <x-nav.link :href="route('admin.finance.level-income')" :active="request()->routeIs('admin.finance.level-income')">Level Income History</x-nav.link>
                <x-nav.link :href="route('admin.finance.eligibility')" :active="request()->routeIs('admin.finance.eligibility')">Referral Eligibility</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Wallet Management" icon="wallet" :open="request()->routeIs('admin.wallets.*')">
                <x-nav.link :href="route('admin.wallets.overview')" :active="request()->routeIs('admin.wallets.overview')">Wallet Overview</x-nav.link>
                <x-nav.link :href="route('admin.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-nav.link>
                <x-nav.link :href="route('admin.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-nav.link>
                <x-nav.link :href="route('admin.wallets.show', ['wallet' => 'activation'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'activation'">Activation Wallet</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Withdrawals" icon="arrow-down-to-line" :open="request()->routeIs('admin.withdrawals.*')">
                <x-nav.link :href="route('admin.withdrawals.overview')" :active="request()->routeIs('admin.withdrawals.overview')">Withdrawal Overview</x-nav.link>
                <x-nav.link :href="route('admin.withdrawals.index')" :active="request()->routeIs('admin.withdrawals.index', 'admin.withdrawals.show')">Withdrawal Requests</x-nav.link>
                <x-nav.link :href="route('admin.withdrawals.settings')" :active="request()->routeIs('admin.withdrawals.settings*')">Withdrawal Settings</x-nav.link>
                <x-nav.link :href="route('admin.withdrawals.payouts')" :active="request()->routeIs('admin.withdrawals.payouts')">Payout History</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Rewards" icon="gift" :open="request()->routeIs('admin.coming-soon') && in_array(request()->route('feature'), ['reward-plans', 'reward-achievements'], true)">
                <x-nav.link :href="route('admin.coming-soon', ['feature' => 'reward-plans'])" :active="request()->route('feature') === 'reward-plans'" soon>Reward Plans</x-nav.link>
                <x-nav.link :href="route('admin.coming-soon', ['feature' => 'reward-achievements'])" :active="request()->route('feature') === 'reward-achievements'" soon>Achievements</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Reports" icon="chart-column" :open="request()->routeIs('admin.catalog-reports.*')">
                <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'members'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'members'">Member Reports</x-nav.link>
                <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'packages'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'packages'">Package Reports</x-nav.link>
                <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'payments'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'payments'">Payment Reports</x-nav.link>
                <x-nav.link :href="route('admin.catalog-reports.show', ['report' => 'earnings'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'earnings'">Earnings Reports</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Settings" icon="settings" :open="request()->routeIs('admin.settings.*', 'admin.change-password.*')">
                <x-nav.link :href="route('admin.settings.general')" :active="request()->routeIs('admin.settings.general')">General Settings</x-nav.link>
                <x-nav.link :href="route('admin.settings.branding')" :active="request()->routeIs('admin.settings.branding')">Branding</x-nav.link>
                <x-nav.link :href="route('admin.settings.security')" :active="request()->routeIs('admin.settings.security', 'admin.change-password.*')">Security</x-nav.link>
                <x-nav.link :href="route('admin.settings.profile')" :active="request()->routeIs('admin.settings.profile')">Admin Profile</x-nav.link>
            </x-nav.group>

            <li>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit"><x-nav.icon name="log-out" /><span>Log out</span></button>
                </form>
            </li>
        </ul>
    </div>
</aside>
