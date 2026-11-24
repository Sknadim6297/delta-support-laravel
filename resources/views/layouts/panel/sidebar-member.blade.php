<aside class="pv-sidebar" aria-label="Member">
    <div class="pv-sidebar-brand">
        <a href="{{ route('member.dashboard') }}">
            <img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support">
            <span class="pv-sidebar-brand-copy">
                <strong>Delta Support</strong>
                <span>Member Panel</span>
            </span>
        </a>
    </div>
    <div class="pv-sidebar-scroll">
        <ul class="pv-nav">
            <li class="pv-nav-item">
                <a href="{{ route('member.dashboard') }}" @class(['pv-nav-link', 'is-active' => request()->routeIs('member.dashboard')])>
                    <i class="ri-dashboard-3-line" aria-hidden="true"></i>
                    <span class="pv-nav-label">Dashboard</span>
                </a>
            </li>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Account</span></li>
            <x-navigation.panel-group icon="ri-user-line" label="My Account" :open="request()->routeIs('member.profile*', 'member.change-password*', 'member.security-pin*')">
                <x-navigation.panel-link :href="route('member.profile')" :active="request()->routeIs('member.profile*')">My Profile</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.change-password')" :active="request()->routeIs('member.change-password*')">Account Security</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.security-pin')" :active="request()->routeIs('member.security-pin*')">Security PIN</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Network</span></li>
            <x-navigation.panel-group icon="ri-share-line" label="My Network" :open="request()->routeIs('member.referral', 'member.registration*', 'member.team.*', 'member.genealogy.*')">
                <x-navigation.panel-link :href="route('member.referral')" :active="request()->routeIs('member.referral')">My Referral Link</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.registration')" :active="request()->routeIs('member.registration*')">Register New Member</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.team.direct')" :active="request()->routeIs('member.team.direct')">Direct Members</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.team.whole')" :active="request()->routeIs('member.team.whole')">Team Overview</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.genealogy.tree-view')" :active="request()->routeIs('member.genealogy.tree-view')">Referral Tree</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Commerce</span></li>
            <x-navigation.panel-group icon="ri-archive-line" label="Packages" :open="request()->routeIs('member.packages.*')">
                <x-navigation.panel-link :href="route('member.packages.explore')" :active="request()->routeIs('member.packages.explore', 'member.packages.show')">Explore Packages</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.packages.mine')" :active="request()->routeIs('member.packages.mine')">My Packages</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-bank-card-line" label="Payments" :open="request()->routeIs('member.payments.*')">
                <x-navigation.panel-link :href="route('member.payments.index')" :active="request()->routeIs('member.payments.*')">My Payments</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-bill-line" label="Invoices" :open="request()->routeIs('member.invoices.*')">
                <x-navigation.panel-link :href="route('member.invoices.index')" :active="request()->routeIs('member.invoices.*')">My Invoices</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">Finance</span></li>
            <x-navigation.panel-group icon="ri-wallet-3-line" label="Earnings" :open="request()->routeIs('member.wallets.*', 'member.income.*', 'member.eligibility', 'member.transfers.*')">
                <x-navigation.panel-link :href="route('member.wallets.overview')" :active="request()->routeIs('member.wallets.overview')">My Wallets</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.transfers.create')" :active="request()->routeIs('member.transfers.create', 'member.transfers.confirm')">Transfer Funds</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.transfers.history')" :active="request()->routeIs('member.transfers.history', 'member.transfers.show')">Transfer History</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.income.roi')" :active="request()->routeIs('member.income.roi')">My ROI History</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.income.level')" :active="request()->routeIs('member.income.level')">My Level Income History</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.eligibility')" :active="request()->routeIs('member.eligibility')">My Referral Eligibility</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-arrow-down-circle-line" label="Withdrawals" :open="request()->routeIs('member.withdrawals.*')">
                <x-navigation.panel-link :href="route('member.withdrawals.request')" :active="request()->routeIs('member.withdrawals.request')">Withdraw USDT</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.withdrawals.history')" :active="request()->routeIs('member.withdrawals.history', 'member.withdrawals.show')">Withdrawal History</x-navigation.panel-link>
            </x-navigation.panel-group>

            <li class="pv-nav-section"><span class="pv-nav-section-label">More</span></li>
            <x-navigation.panel-group icon="ri-gift-line" label="Rewards" :open="request()->routeIs('member.rewards.*')">
                <x-navigation.panel-link :href="route('member.rewards.index')" :active="request()->routeIs('member.rewards.*')">My Rewards</x-navigation.panel-link>
            </x-navigation.panel-group>
            <x-navigation.panel-group icon="ri-customer-service-2-line" label="Support" :open="request()->routeIs('member.support*')">
                <x-navigation.panel-link :href="route('member.support')" :active="request()->routeIs('member.support')">My Support Tickets</x-navigation.panel-link>
                <x-navigation.panel-link :href="route('member.support.create')" :active="request()->routeIs('member.support.create')">New Ticket</x-navigation.panel-link>
            </x-navigation.panel-group>
        </ul>
    </div>
</aside>
