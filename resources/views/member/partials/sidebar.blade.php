<div class="lh-sidebar-overlay"></div>
<aside class="lh-sidebar" data-mode="light" aria-label="Member">
    <div class="lh-sb-logo">
        <a href="{{ route('member.dashboard') }}" class="sb-full"><img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support"></a>
        <a href="{{ route('member.dashboard') }}" class="sb-collapse"><img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support"></a>
    </div>
    <div class="lh-sb-wrapper">
        <div class="lh-sb-content">
            <ul class="lh-sb-list">
                <li class="lh-sb-item {{ request()->routeIs('member.dashboard') ? 'load-active' : '' }}">
                    <a href="{{ route('member.dashboard') }}" class="lh-page-link">
                        <i class="ri-dashboard-3-line"></i>
                        <span class="condense"><span class="hover-title">Dashboard</span></span>
                    </a>
                </li>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Account</li>
                <x-navigation.lux-group icon="ri-user-line" label="My Account" :open="request()->routeIs('member.profile*', 'member.change-password*', 'member.security-pin*')">
                    <x-navigation.lux-link :href="route('member.profile')" :active="request()->routeIs('member.profile*')">My Profile</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.change-password')" :active="request()->routeIs('member.change-password*')">Account Security</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.security-pin')" :active="request()->routeIs('member.security-pin*')">Security PIN</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Network</li>
                <x-navigation.lux-group icon="ri-share-line" label="My Network" :open="request()->routeIs('member.referral', 'member.registration*', 'member.team.*', 'member.genealogy.*')">
                    <x-navigation.lux-link :href="route('member.referral')" :active="request()->routeIs('member.referral')">My Referral Link</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.registration')" :active="request()->routeIs('member.registration*')">Register New Member</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.team.direct')" :active="request()->routeIs('member.team.direct')">Direct Members</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.team.whole')" :active="request()->routeIs('member.team.whole')">Team Overview</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.genealogy.tree-view')" :active="request()->routeIs('member.genealogy.tree-view')">Referral Tree</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Commerce</li>
                <x-navigation.lux-group icon="ri-archive-line" label="Packages" :open="request()->routeIs('member.packages.*')">
                    <x-navigation.lux-link :href="route('member.packages.explore')" :active="request()->routeIs('member.packages.explore', 'member.packages.show')">Explore Packages</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.packages.mine')" :active="request()->routeIs('member.packages.mine')">My Packages</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-bank-card-line" label="Payments" :open="request()->routeIs('member.payments.*')">
                    <x-navigation.lux-link :href="route('member.payments.index')" :active="request()->routeIs('member.payments.*')">My Payments</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-bill-line" label="Invoices" :open="request()->routeIs('member.invoices.*')">
                    <x-navigation.lux-link :href="route('member.invoices.index')" :active="request()->routeIs('member.invoices.*')">My Invoices</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">Finance</li>
                <x-navigation.lux-group icon="ri-wallet-3-line" label="Earnings" :open="request()->routeIs('member.wallets.*', 'member.income.*', 'member.eligibility', 'member.transfers.*')">
                    <x-navigation.lux-link :href="route('member.wallets.overview')" :active="request()->routeIs('member.wallets.overview')">My Wallets</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.transfers.create')" :active="request()->routeIs('member.transfers.create', 'member.transfers.confirm')">Transfer Funds</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.transfers.history')" :active="request()->routeIs('member.transfers.history', 'member.transfers.show')">Transfer History</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.income.roi')" :active="request()->routeIs('member.income.roi')">My ROI History</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.income.level')" :active="request()->routeIs('member.income.level')">My Level Income History</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.eligibility')" :active="request()->routeIs('member.eligibility')">My Referral Eligibility</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-arrow-down-circle-line" label="Withdrawals" :open="request()->routeIs('member.withdrawals.*')">
                    <x-navigation.lux-link :href="route('member.withdrawals.request')" :active="request()->routeIs('member.withdrawals.request')">Withdraw USDT</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.withdrawals.history')" :active="request()->routeIs('member.withdrawals.history', 'member.withdrawals.show')">Withdrawal History</x-navigation.lux-link>
                </x-navigation.lux-group>

                <li class="lh-sb-item-separator"></li>
                <li class="lh-sb-title condense">More</li>
                <x-navigation.lux-group icon="ri-gift-line" label="Rewards" :open="request()->routeIs('member.rewards.*')">
                    <x-navigation.lux-link :href="route('member.rewards.index')" :active="request()->routeIs('member.rewards.*')">My Rewards</x-navigation.lux-link>
                </x-navigation.lux-group>
                <x-navigation.lux-group icon="ri-customer-service-2-line" label="Support" :open="request()->routeIs('member.support*')">
                    <x-navigation.lux-link :href="route('member.support')" :active="request()->routeIs('member.support')">My Support Tickets</x-navigation.lux-link>
                    <x-navigation.lux-link :href="route('member.support.create')" :active="request()->routeIs('member.support.create')">New Ticket</x-navigation.lux-link>
                </x-navigation.lux-group>
            </ul>
        </div>
    </div>
</aside>
