<aside id="desktopSidebar" class="sidebar desktop-sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('site/img/logo.png') }}" class="sidebar-logo-image" alt="Delta Support">
    </div>
    <div class="px-3 pb-2 text-center text-sm font-semibold text-white">{{ $member->member_id ?? session('member_context_id') }}</div>

    <nav class="sidebar-menu ds-nav" aria-label="Member">
        <x-nav.link :href="route('member.dashboard')" :active="request()->routeIs('member.dashboard')">Dashboard</x-nav.link>

        <x-nav.group label="Account" icon="bi-person" :open="request()->routeIs('member.profile*', 'member.change-password*')">
            <x-nav.link :href="route('member.profile')" :active="request()->routeIs('member.profile*')">My Profile</x-nav.link>
            <x-nav.link :href="route('member.change-password')" :active="request()->routeIs('member.change-password*')">Account Security</x-nav.link>
        </x-nav.group>

        <x-nav.group label="My Network" icon="bi-diagram-3" :open="request()->routeIs('member.referral', 'member.team.*')">
            <x-nav.link :href="route('member.referral')" :active="request()->routeIs('member.referral')">My Referral Link</x-nav.link>
            <x-nav.link :href="route('member.team.direct')" :active="request()->routeIs('member.team.direct')">Direct Members</x-nav.link>
            <x-nav.link :href="route('member.team.whole')" :active="request()->routeIs('member.team.whole')">Team Overview</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Packages" icon="bi-box-seam" :open="request()->routeIs('member.packages.*')">
            <x-nav.link :href="route('member.packages.explore')" :active="request()->routeIs('member.packages.explore', 'member.packages.show')">Explore Packages</x-nav.link>
            <x-nav.link :href="route('member.packages.mine')" :active="request()->routeIs('member.packages.mine')">My Packages</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Payments" icon="bi-qr-code" :open="request()->routeIs('member.payments.*')">
            <x-nav.link :href="route('member.payments.index')" :active="request()->routeIs('member.payments.*')">My Payments</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Invoices" icon="bi-receipt" :open="request()->routeIs('member.invoices.*')">
            <x-nav.link :href="route('member.invoices.index')" :active="request()->routeIs('member.invoices.*')">My Invoices</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Earnings" icon="bi-wallet2">
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'roi-wallet'])" soon>ROI Wallet</x-nav.link>
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'working-wallet'])" soon>Working Wallet</x-nav.link>
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'my-level-income'])" soon>My Level Income</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Withdrawals" icon="bi-cash-coin">
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'request-withdrawal'])" soon>Request Withdrawal</x-nav.link>
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'withdrawal-history'])" soon>Withdrawal History</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Tours" icon="bi-airplane" :open="request()->routeIs('member.tours')">
            <x-nav.link :href="route('member.tours')" :active="request()->routeIs('member.tours')">My Tour Benefits</x-nav.link>
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'booking-requests'])" soon>Booking Requests</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Rewards" icon="bi-award">
            <x-nav.link :href="route('member.coming-soon', ['feature' => 'my-rewards'])" soon>My Rewards</x-nav.link>
        </x-nav.group>

        <x-nav.group label="Support" icon="bi-headset" :open="request()->routeIs('member.support')">
            <x-nav.link :href="route('member.support')" :active="request()->routeIs('member.support')">Help & Support</x-nav.link>
        </x-nav.group>

        <form action="{{ route('member.logout') }}" method="POST">
            @csrf
            <button type="submit"><i class="bi bi-box-arrow-right"></i><span>Log out</span></button>
        </form>
    </nav>
</aside>
