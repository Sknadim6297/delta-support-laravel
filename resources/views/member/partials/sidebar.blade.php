<aside id="dsSidebar" aria-label="Member">
    <div class="ds-brand">
        <a href="{{ route('member.dashboard') }}">
            <img class="ds-logo" src="{{ asset('site/img/logo.png') }}" alt="Delta Support">
        </a>
        <button type="button" class="ds-sidebar-close" aria-label="Close menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
    <div class="ds-sidebar-scroll">
        <ul class="ds-menu">
            <x-nav.link single icon="layout-dashboard" :href="route('member.dashboard')" :active="request()->routeIs('member.dashboard')">Dashboard</x-nav.link>

            <x-nav.group label="Account" icon="user" :open="request()->routeIs('member.profile*', 'member.change-password*')">
                <x-nav.link :href="route('member.profile')" :active="request()->routeIs('member.profile*')">My Profile</x-nav.link>
                <x-nav.link :href="route('member.change-password')" :active="request()->routeIs('member.change-password*')">Account Security</x-nav.link>
            </x-nav.group>

            <x-nav.group label="My Network" icon="network" :open="request()->routeIs('member.referral', 'member.registration*', 'member.team.*', 'member.genealogy.*')">
                <x-nav.link :href="route('member.referral')" :active="request()->routeIs('member.referral')">My Referral Link</x-nav.link>
                <x-nav.link :href="route('member.registration')" :active="request()->routeIs('member.registration*')">Register New Member</x-nav.link>
                <x-nav.link :href="route('member.team.direct')" :active="request()->routeIs('member.team.direct')">Direct Members</x-nav.link>
                <x-nav.link :href="route('member.team.whole')" :active="request()->routeIs('member.team.whole')">Team Overview</x-nav.link>
                <x-nav.link :href="route('member.genealogy.tree-view')" :active="request()->routeIs('member.genealogy.tree-view')">Referral Tree</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Packages" icon="package" :open="request()->routeIs('member.packages.*')">
                <x-nav.link :href="route('member.packages.explore')" :active="request()->routeIs('member.packages.explore', 'member.packages.show')">Explore Packages</x-nav.link>
                <x-nav.link :href="route('member.packages.mine')" :active="request()->routeIs('member.packages.mine')">My Packages</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Payments" icon="credit-card" :open="request()->routeIs('member.payments.*')">
                <x-nav.link :href="route('member.payments.index')" :active="request()->routeIs('member.payments.*')">My Payments</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Invoices" icon="receipt" :open="request()->routeIs('member.invoices.*')">
                <x-nav.link :href="route('member.invoices.index')" :active="request()->routeIs('member.invoices.*')">My Invoices</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Earnings" icon="wallet" :open="request()->routeIs('member.wallets.*', 'member.income.*', 'member.eligibility')">
                <x-nav.link :href="route('member.wallets.overview')" :active="request()->routeIs('member.wallets.overview')">My Wallets</x-nav.link>
                <x-nav.link :href="route('member.wallets.show', ['wallet' => 'roi'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'roi'">ROI Wallet</x-nav.link>
                <x-nav.link :href="route('member.wallets.show', ['wallet' => 'working'])" :active="request()->routeIs('member.wallets.show') && request()->route('wallet') === 'working'">Working Wallet</x-nav.link>
                <x-nav.link :href="route('member.income.roi')" :active="request()->routeIs('member.income.roi')">My ROI History</x-nav.link>
                <x-nav.link :href="route('member.income.level')" :active="request()->routeIs('member.income.level')">My Level Income History</x-nav.link>
                <x-nav.link :href="route('member.eligibility')" :active="request()->routeIs('member.eligibility')">My Referral Eligibility</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Withdrawals" icon="arrow-down-to-line" :open="request()->routeIs('member.withdrawals.*')">
                <x-nav.link :href="route('member.withdrawals.request')" :active="request()->routeIs('member.withdrawals.request')">Withdraw USDT</x-nav.link>
                <x-nav.link :href="route('member.withdrawals.history')" :active="request()->routeIs('member.withdrawals.history', 'member.withdrawals.show')">Withdrawal History</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Rewards" icon="gift" :open="request()->routeIs('member.coming-soon') && request()->route('feature') === 'my-rewards'">
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'my-rewards'])" :active="request()->route('feature') === 'my-rewards'" soon>My Rewards</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Support" icon="life-buoy" :open="request()->routeIs('member.support')">
                <x-nav.link :href="route('member.support')" :active="request()->routeIs('member.support')">Help & Support</x-nav.link>
            </x-nav.group>

            <li>
                <form action="{{ route('member.logout') }}" method="POST">
                    @csrf
                    <button type="submit"><x-nav.icon name="log-out" /><span>Log out</span></button>
                </form>
            </li>
        </ul>
    </div>
</aside>
