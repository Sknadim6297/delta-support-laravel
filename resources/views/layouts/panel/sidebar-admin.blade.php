<aside class="pv-sidebar" aria-label="Admin">
    <div class="pv-sidebar-brand">
        <a href="{{ route('dashboard') }}">
            <img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support">
            <span class="pv-sidebar-brand-copy">
                <strong>Delta Support</strong>
                <span>Admin Console</span>
            </span>
        </a>
    </div>
    <div class="pv-sidebar-scroll">
        <ul class="pv-nav">
            <li class="pv-nav-item">
                <a href="{{ route('dashboard') }}" @class(['pv-nav-link', 'is-active' => request()->routeIs('dashboard')])>
                    <i class="ri-dashboard-3-line" aria-hidden="true"></i>
                    <span class="pv-nav-label">Dashboard</span>
                </a>
            </li>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Members</span></li>
            <x-navigation.panel-group icon="ri-group-line" label="Member Management" :open="request()->routeIs('admin.members.*', 'admin.genealogy.tree-view')">
                <x-navigation.panel-link :href="route('admin.members.directory')" :active="request()->routeIs('admin.members.directory')">All Members</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.members.registration')" :active="request()->routeIs('admin.members.registration')">Member Registration</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.members.update')" :active="request()->routeIs('admin.members.update')">View / Update Member Profile</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.members.inactive')" :active="request()->routeIs('admin.members.inactive')">Inactive Member List</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.members.active')" :active="request()->routeIs('admin.members.active')">Active Member List</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.genealogy.tree-view')" :active="request()->routeIs('admin.genealogy.tree-view')">Referral Tree</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Commerce</span></li>
            <x-navigation.panel-group icon="ri-archive-line" label="Package Management" :open="request()->routeIs('admin.packages.*', 'admin.purchases.*')">
                <x-navigation.panel-link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.index')">All Packages</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.packages.create')" :active="request()->routeIs('admin.packages.create')">Add Package</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.purchases.index')" :active="request()->routeIs('admin.purchases.index')">Package Purchases</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.purchases.create')" :active="request()->routeIs('admin.purchases.create')">Purchase for Customer</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-bank-card-line" label="Payment Management" :open="request()->routeIs('admin.payments.*')">
                <x-navigation.panel-link :href="route('admin.payments.index', ['status' => 'pending'])" :active="request()->routeIs('admin.payments.index') && request()->query('status', 'pending') === 'pending'">Pending Payments</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.payments.index', ['status' => 'approved'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'approved'">Approved Payments</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.payments.index', ['status' => 'rejected'])" :active="request()->routeIs('admin.payments.index') && request()->query('status') === 'rejected'">Rejected Payments</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.payments.settings')" :active="request()->routeIs('admin.payments.settings', 'admin.payments.qr')">Payment Settings</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-bill-line" label="Invoices" :open="request()->routeIs('admin.invoices.*')">
                <x-navigation.panel-link :href="route('admin.invoices.index')" :active="request()->routeIs('admin.invoices.*')">All Invoices</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Finance</span></li>
            <x-navigation.panel-group icon="ri-line-chart-line" label="ROI Management" :open="request()->routeIs('admin.finance.roi-history', 'admin.finance.roi-plans', 'admin.finance.roi-schedule', 'admin.finance.real-income', 'admin.finance.income-test')">
                <x-navigation.panel-link :href="route('admin.finance.roi-plans')" :active="request()->routeIs('admin.finance.roi-plans')">ROI Plans</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.roi-schedule')" :active="request()->routeIs('admin.finance.roi-schedule')">Income Control</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.real-income')" :active="request()->routeIs('admin.finance.real-income')">Real Income</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.roi-history')" :active="request()->routeIs('admin.finance.roi-history')">ROI History</x-navigation.panel-link>
                @unless (app()->environment('production'))
                    <x-navigation.panel-link :href="route('admin.finance.income-test')" :active="request()->routeIs('admin.finance.income-test')">Income Scheduler Testing</x-navigation.panel-link>
                @endunless
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-node-tree" label="Level Income" :open="request()->routeIs('admin.finance.level-income', 'admin.finance.eligibility', 'admin.finance.commission-settings', 'admin.finance.level-unlock-rules', 'admin.demo-income')">
                <x-navigation.panel-link :href="route('admin.finance.commission-settings')" :active="request()->routeIs('admin.finance.commission-settings')">Commission Settings</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.level-unlock-rules')" :active="request()->routeIs('admin.finance.level-unlock-rules')">Level Unlock Rules</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.level-income')" :active="request()->routeIs('admin.finance.level-income')">Level Income History</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.finance.eligibility')" :active="request()->routeIs('admin.finance.eligibility')">Referral Eligibility</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.demo-income')" :active="request()->routeIs('admin.demo-income')">Demo Income Preview</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-wallet-3-line" label="Wallet Management" :open="request()->routeIs('admin.wallets.*', 'admin.member-transfers.*')">
                <x-navigation.panel-link :href="route('admin.wallets.overview')" :active="request()->routeIs('admin.wallets.overview')">Wallet Overview</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.wallets.show', ['wallet' => 'activation'])" :active="request()->routeIs('admin.wallets.show') && request()->route('wallet') === 'activation'">Activation Wallet</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.member-transfers.index')" :active="request()->routeIs('admin.member-transfers.*')">Member Transfers</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-arrow-down-circle-line" label="Withdrawals" :open="request()->routeIs('admin.withdrawals.*')">
                <x-navigation.panel-link :href="route('admin.withdrawals.overview')" :active="request()->routeIs('admin.withdrawals.overview')">Withdrawal Overview</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.withdrawals.index')" :active="request()->routeIs('admin.withdrawals.index', 'admin.withdrawals.show')">Withdrawal Requests</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.withdrawals.settings')" :active="request()->routeIs('admin.withdrawals.settings*')">Withdrawal Settings</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.withdrawals.payouts')" :active="request()->routeIs('admin.withdrawals.payouts')">Payout History</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Insights</span></li>
            <x-navigation.panel-group icon="ri-gift-line" label="Rewards" :open="request()->routeIs('admin.rewards.*')">
                <x-navigation.panel-link :href="route('admin.rewards.index')" :active="request()->routeIs('admin.rewards.index', 'admin.rewards.show', 'admin.rewards.create', 'admin.rewards.edit')">Reward Campaigns</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.rewards.eligible')" :active="request()->routeIs('admin.rewards.eligible', 'admin.rewards.qualifications.*')">Eligible Members</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.rewards.reports')" :active="request()->routeIs('admin.rewards.reports')">Reward Reports</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-bar-chart-box-line" label="Reports" :open="request()->routeIs('admin.catalog-reports.*')">
                <x-navigation.panel-link :href="route('admin.catalog-reports.show', ['report' => 'members'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'members'">Member Reports</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.catalog-reports.show', ['report' => 'packages'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'packages'">Package Reports</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.catalog-reports.show', ['report' => 'payments'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'payments'">Payment Reports</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.catalog-reports.show', ['report' => 'earnings'])" :active="request()->routeIs('admin.catalog-reports.show') && request()->route('report') === 'earnings'">Earnings Reports</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Support</span></li>
            <x-navigation.panel-group icon="ri-customer-service-2-line" label="Support Tickets" :open="request()->routeIs('admin.support.*')">
                <x-navigation.panel-link :href="route('admin.support.index')" :active="request()->routeIs('admin.support.index') && request('status') === null">All Tickets</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.support.open')" :active="request('status') === 'open'">Open</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.support.pending-tickets')" :active="request('status') === 'awaiting_admin'">Awaiting Admin</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.support.index', ['status' => 'awaiting_member'])" :active="request('status') === 'awaiting_member'">Awaiting Member</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.support.applied-tickets')" :active="request('status') === 'resolved'">Resolved</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.support.index', ['status' => 'closed'])" :active="request('status') === 'closed'">Closed</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Account</span></li>
            <x-navigation.panel-group icon="ri-settings-3-line" label="Settings" :open="request()->routeIs('admin.settings.*', 'admin.change-password.*')">
                <x-navigation.panel-link :href="route('admin.settings.general')" :active="request()->routeIs('admin.settings.general')">General Settings</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.settings.financial')" :active="request()->routeIs('admin.settings.financial')">Financial Controls</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.settings.branding')" :active="request()->routeIs('admin.settings.branding')">Branding</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.settings.security')" :active="request()->routeIs('admin.settings.security', 'admin.change-password.*')">Security</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('admin.settings.profile')" :active="request()->routeIs('admin.settings.profile')">Admin Profile</x-navigation.panel-link>
            </x-navigation.panel-group>
        </ul>
    </div>
</aside>
