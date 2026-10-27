<aside class="ds-sidebar" id="dsSidebar" aria-label="Member">
    <div class="ds-sidebar-head">
        <a href="{{ route('member.dashboard') }}">
            <img src="{{ asset('site/img/logo.png') }}" alt="Delta Support">
        </a>
        <button type="button" class="ds-sidebar-close" aria-label="Close menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
    <div class="ds-sidebar-user">
        <span class="ds-avatar">{{ strtoupper(substr($member->member_name ?? 'M', 0, 1)) }}</span>
        <div>
            <strong>{{ $member->member_name ?? 'Member' }}</strong>
            <small>{{ $member->member_id ?? session('member_context_id') }}</small>
        </div>
    </div>
    <div class="ds-sidebar-scroll">
        <ul class="ds-menu">
            <x-nav.link single icon="layout-dashboard" :href="route('member.dashboard')" :active="request()->routeIs('member.dashboard')">Dashboard</x-nav.link>

            <x-nav.group label="Account" icon="user" :open="request()->routeIs('member.profile*', 'member.change-password*')">
                <x-nav.link :href="route('member.profile')" :active="request()->routeIs('member.profile*')">My Profile</x-nav.link>
                <x-nav.link :href="route('member.change-password')" :active="request()->routeIs('member.change-password*')">Account Security</x-nav.link>
            </x-nav.group>

            <x-nav.group label="My Network" icon="network" :open="request()->routeIs('member.referral', 'member.team.*')">
                <x-nav.link :href="route('member.referral')" :active="request()->routeIs('member.referral')">My Referral Link</x-nav.link>
                <x-nav.link :href="route('member.team.direct')" :active="request()->routeIs('member.team.direct')">Direct Members</x-nav.link>
                <x-nav.link :href="route('member.team.whole')" :active="request()->routeIs('member.team.whole')">Team Overview</x-nav.link>
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

            <x-nav.group label="Earnings" icon="wallet">
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'roi-wallet'])" soon>ROI Wallet</x-nav.link>
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'working-wallet'])" soon>Working Wallet</x-nav.link>
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'my-level-income'])" soon>My Level Income</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Withdrawals" icon="arrow-down-to-line">
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'request-withdrawal'])" soon>Request Withdrawal</x-nav.link>
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'withdrawal-history'])" soon>Withdrawal History</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Rewards" icon="gift">
                <x-nav.link :href="route('member.coming-soon', ['feature' => 'my-rewards'])" soon>My Rewards</x-nav.link>
            </x-nav.group>

            <x-nav.group label="Support" icon="life-buoy" :open="request()->routeIs('member.support')">
                <x-nav.link :href="route('member.support')" :active="request()->routeIs('member.support')">Help & Support</x-nav.link>
            </x-nav.group>

            <li class="ds-item">
                <form action="{{ route('member.logout') }}" method="POST">
                    @csrf
                    <button type="submit"><x-nav.icon name="log-out" /><span>Log out</span></button>
                </form>
            </li>
        </ul>
    </div>
</aside>
