<div class="lh-sidebar-overlay"></div>
<aside class="lh-sidebar" data-mode="light" aria-label="Admin">
    <div class="lh-sb-logo">
        <a href="{{ route('dashboard') }}" class="sb-full"><img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support"></a>
        <a href="{{ route('dashboard') }}" class="sb-collapse"><img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support"></a>
    </div>
    <div class="lh-sb-wrapper">
        <div class="lh-sb-content">
            <ul class="lh-sb-list">
                <li class="lh-sb-item {{ request()->routeIs('dashboard') ? 'load-active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="lh-page-link">
                        <i class="ri-dashboard-3-line"></i>
                        <span class="condense"><span class="hover-title">Dashboard</span></span>
                    </a>
                </li>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Members</li>
                <x-navigation.lux-group icon="ri-group-line" label="Member Management" :open="request()->routeIs('admin.members.*', 'admin.genealogy.tree-view')">
                    <x-navigation.lux-link :href="route('admin.members.directory')" :active="request()->routeIs('admin.members.directory')">All Members</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.members.registration')" :active="request()->routeIs('admin.members.registration')">Member Registration</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.members.update')" :active="request()->routeIs('admin.members.update')">View / Update Member Profile</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.members.inactive')" :active="request()->routeIs('admin.members.inactive')">Inactive Member List</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.members.active')" :active="request()->routeIs('admin.members.active')">Active Member List</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.genealogy.tree-view')" :active="request()->routeIs('admin.genealogy.tree-view')">Referral Tree</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Commerce</li>
                <x-navigation.lux-group icon="ri-archive-line" label="Package Management" :open="request()->routeIs('admin.packages.*', 'admin.purchases.*')">
                    <x-navigation.lux-link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.index')">All Packages</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.packages.create')" :active="request()->routeIs('admin.packages.create')">Add Package</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.purchases.index')" :active="request()->routeIs('admin.purchases.*')">Package Purchases</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-bank-card-line" label="Payment Management" :open="request()->routeIs('admin.payments.*')">
                    <x-navigation.lux-link :href="route('admin.payments.index', ['status' => 'pending'])" :active="request()->routeIs('admin.payments.index') && request()->query('status', 'pending') === 'pending'">Pending Payments</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.payments.index', ['status' => 'approved'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'approved'">Approved Payments</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.payments.index', ['status' => 'rejected'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'rejected'">Rejected Payments</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.payments.settings')" :active="request()->routeIs('admin.payments.settings', 'admin.payments.qr')">Payment Settings</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-bill-line" label="Invoices" :open="request()->routeIs('admin.invoices.*')">
                    <x-navigation.lux-link :href="route('admin.invoices.index')" :active="request()->routeIs('admin.invoices.*')">All Invoices</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Finance</li>
                <x-navigation.lux-group icon="ri-line-chart-line" label="ROI Management" :open="request()->routeIs('admin.finance.roi-history', 'admin.finance.roi-plans', 'admin.finance.roi-schedule', 'admin.finance.income-test')">
                    <x-navigation.lux-link :href="route('admin.finance.roi-plans')" :active="request()->routeIs('admin.finance.roi-plans')">ROI Plans</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.finance.roi-schedule')" :active="request()->routeIs('admin.finance.roi-schedule')">Income Control</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.finance.roi-history')" :active="request()->routeIs('admin.finance.roi-history')">ROI History</x-navigation.lux-link>
                    @unless (app()->environment('production'))
                        <x-navigation.lux-link :href="route('admin.finance.income-test')" :active="request()->routeIs('admin.finance.income-test')">Income Scheduler Testing</x-navigation.lux-link>
                    @endunless
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-node-tree" label="Level Income" :open="request()->routeIs('admin.finance.level-income', 'admin.finance.eligibility', 'admin.finance.commission-settings', 'admin.finance.level-unlock-rules')">
                    <x-navigation.lux-link :href="route('admin.finance.commission-settings')" :active="request()->routeIs('admin.finance.commission-settings')">Commission Settings</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.finance.level-unlock-rules')" :active="request()->routeIs('admin.finance.level-unlock-rules')">Level Unlock Rules</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.finance.level-income')" :active="request()->routeIs('admin.finance.level-income')">Level Income History</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.finance.eligibility')" :active="request()->routeIs('admin.finance.eligibility')">Referral Eligibility</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-wallet-3-line" label="Wallet Management" :open="request()->routeIs('admin.wallets.*')">
                    <x-navigation.lux-link :href="route('admin.wallets.overview')" :active="request()->routeIs('admin.wallets.overview')">Wallet Overview</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.wallets.show', ['wallet' => 'activation'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'activation'">Activation Wallet</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-arrow-down-circle-line" label="Withdrawals" :open="request()->routeIs('admin.withdrawals.*')">
                    <x-navigation.lux-link :href="route('admin.withdrawals.overview')" :active="request()->routeIs('admin.withdrawals.overview')">Withdrawal Overview</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.withdrawals.index')" :active="request()->routeIs('admin.withdrawals.index', 'admin.withdrawals.show')">Withdrawal Requests</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.withdrawals.settings')" :active="request()->routeIs('admin.withdrawals.settings*')">Withdrawal Settings</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.withdrawals.payouts')" :active="request()->routeIs('admin.withdrawals.payouts')">Payout History</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Insights</li>
                <x-navigation.lux-group icon="ri-gift-line" label="Rewards" :open="request()->routeIs('admin.rewards.*')">
                    <x-navigation.lux-link :href="route('admin.rewards.index')" :active="request()->routeIs('admin.rewards.index', 'admin.rewards.show', 'admin.rewards.create', 'admin.rewards.edit')">Reward Campaigns</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.rewards.eligible')" :active="request()->routeIs('admin.rewards.eligible', 'admin.rewards.qualifications.*')">Eligible Members</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.rewards.reports')" :active="request()->routeIs('admin.rewards.reports')">Reward Reports</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-bar-chart-box-line" label="Reports" :open="request()->routeIs('admin.catalog-reports.*')">
                    <x-navigation.lux-link :href="route('admin.catalog-reports.show', ['report' => 'members'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'members'">Member Reports</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.catalog-reports.show', ['report' => 'packages'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'packages'">Package Reports</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.catalog-reports.show', ['report' => 'payments'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'payments'">Payment Reports</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.catalog-reports.show', ['report' => 'earnings'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'earnings'">Earnings Reports</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Account</li>
                <x-navigation.lux-group icon="ri-settings-3-line" label="Settings" :open="request()->routeIs('admin.settings.*', 'admin.change-password.*')">
                    <x-navigation.lux-link :href="route('admin.settings.general')" :active="request()->routeIs('admin.settings.general')">General Settings</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.settings.financial')" :active="request()->routeIs('admin.settings.financial')">Financial Controls</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.settings.branding')" :active="request()->routeIs('admin.settings.branding')">Branding</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.settings.security')" :active="request()->routeIs('admin.settings.security', 'admin.change-password.*')">Security</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('admin.settings.profile')" :active="request()->routeIs('admin.settings.profile')">Admin Profile</x-navigation.lux-link>
                </x-navigation.lux-group>
            </ul>
        </div>
    </div>
</aside>
