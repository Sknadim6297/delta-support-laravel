@extends('member.layouts.app')

@section('title', 'Member Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $hasApprovedPackage = $approvedPackage !== null;
    $accountActive = $member->status === 'active';
    $packageStatus = $hasApprovedPackage
        ? 'Approved package'
        : ($recentPurchases->contains(fn ($purchase) => $purchase->status === \App\Models\PackagePurchase::PENDING) ? 'Pending review' : 'No active package');
@endphp
<div class="ds-dash">
    <x-ui.page-header
        heading
        :title="'Welcome back, '.$member->member_name.'!'"
        :lead="$member->member_id.' · Sponsor '.$member->sponsor_id.' · '.$member->sponsor_name"
    />
    @if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid'))
        <x-ui.alert type="warning">Local test data. Production payout generation remains disabled.</x-ui.alert>
    @endif

    @if (! $accountActive)
        <section class="ds-activation-banner" role="status">
            <div>
                <x-ui.badge tone="warning">Inactive</x-ui.badge>
                <h2>Account Inactive — No Active Package</h2>
                <p>Your account is currently inactive because you don't have an active package. Buy a package to unlock eligible benefits and start your membership journey.</p>
            </div>
            <div class="ds-alert-actions">
                <x-ui.button :href="route('member.packages.explore')">Buy a Package</x-ui.button>
                <x-ui.button variant="secondary" :href="route('member.packages.explore')">Explore Packages</x-ui.button>
            </div>
        </section>
    @else
        <section class="ds-activation-banner is-active" role="status">
            <div>
                <x-ui.badge tone="success">Active</x-ui.badge>
                <h2>Account Active</h2>
                <p>Package-dependent income appears here only after it has been calculated and stored. Opening this dashboard does not credit ROI or level income.</p>
            </div>
        </section>
    @endif

    <section class="ds-dash-kpis" aria-label="Account">
        <x-ui.stat label="Member ID" :value="$member->member_id" />
        <x-ui.stat label="Member Name" :value="$member->member_name" />
        <x-ui.stat label="Sponsor" :value="$member->sponsor_id" :hint="$member->sponsor_name" />
        <x-ui.stat label="Registered" :value="$member->created_at?->timezone('Asia/Kolkata')->format('d M Y') ?? 'N/A'" />
        <x-ui.stat label="Account Status" :value="$accountActive ? 'Active' : 'Inactive'" />
        <x-ui.stat label="Package Status" :value="$packageStatus" />
        <x-ui.stat label="Direct Referrals" :value="(string) $directReferrals" hint="{{ $metrics['activeMembers'] }} active · {{ $metrics['inactiveMembers'] }} inactive" />
    </section>

    <div class="ds-dash-columns">
        <x-ui.card title="{{ $hasApprovedPackage ? 'Active package' : 'Package status' }}">
            @if ($hasApprovedPackage)
                <dl class="ds-detail">
                    <div><dt>Package</dt><dd>{{ $approvedPackage->package_name }}</dd></div>
                    <div><dt>Price</dt><dd>{{ \App\Support\MoneyDisplay::format($approvedPackage->price_usd, $approvedPackage->denomination ?: 'USDT') }}</dd></div>
                    <div><dt>Activated</dt><dd>{{ $approvedPackage->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?? 'Recorded' }}</dd></div>
                    <div><dt>Duration on record</dt><dd>{{ $approvedPackage->duration ?: 'Not recorded' }}</dd></div>
                    <div><dt>Benefits on record</dt><dd>{{ $approvedPackage->benefits ?: 'Not recorded' }}</dd></div>
                </dl>
            @else
                <p>No approved package is stored for this account. A submitted payment stays pending until an administrator verifies it.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Referral link">
            <p>Share this link. A registration under your ID does not purchase a package or create commission.</p>
            <div class="ds-referral-row">
                <input type="text" value="{{ $metrics['referralUrl'] }}" readonly>
                <button type="button" class="ds-btn ds-btn-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
            </div>
        </x-ui.card>
    </div>

    <section>
        <x-ui.section title="Available packages" lead="These are the packages currently saved as available. Prices and benefits come from that record, not from a new calculation." />
        @if ($packageOptions->isEmpty())
            <x-ui.empty title="No packages are available" message="An administrator has not published an active package." />
        @else
            <div class="ds-package-grid">
                @foreach ($packageOptions as $package)
                    <article class="ds-package-card">
                        <p class="ds-package-code">{{ $package->code }}</p>
                        <h3>{{ $package->name }}</h3>
                        <p class="ds-package-price">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination ?: 'USDT') }}</p>
                        <p class="ds-package-note">{{ $package->duration ?: 'Duration is stored with the package record.' }}</p>
                        <x-ui.button variant="secondary" size="sm" :href="route('member.packages.show', $package)">View package</x-ui.button>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="ds-dash-columns">
        <x-ui.card title="ROI summary">
            <p class="ds-stat-value">{{ \App\Support\MoneyDisplay::usdt($metrics['roiIncome']) }}</p>
            <p>{{ $hasApprovedPackage ? 'This is income already stored. Opening this page does not calculate or credit ROI.' : 'ROI accrual is locked until an eligible package is approved. Nothing is credited from this page.' }}</p>
        </x-ui.card>
        <x-ui.card title="Level income summary">
            <p class="ds-stat-value">{{ \App\Support\MoneyDisplay::usdt($metrics['levelIncome']) }}</p>
            <p>Stored level income only. The business-plan poster describes 20 levels for a $100 package, with 1 direct opening 4 levels. That schedule is not activated here.</p>
        </x-ui.card>
    </div>

    <section id="activation-wallet">
        <x-ui.section title="Wallet overview" lead="Balances already stored on the account." />
        <div class="ds-dash-wallets">
            <x-ui.stat id="roi-wallet" label="ROI Wallet" :value="\App\Support\MoneyDisplay::usdt($metrics['roiWallet'])" />
            <x-ui.stat id="working-wallet" label="Working Wallet" :value="\App\Support\MoneyDisplay::usdt($metrics['workingWallet'])" />
            <x-ui.stat id="salary-wallet" label="Salary Wallet" :value="\App\Support\MoneyDisplay::usdt($metrics['salaryWallet'])" />
            <x-ui.stat label="Activation Wallet" :value="\App\Support\MoneyDisplay::usdt($metrics['activationWallet'])" />
        </div>
    </section>

    <section id="investment">
        <x-ui.section title="Recorded totals" lead="Historical investment figures stay in their saved denomination display." />
        <div class="ds-dash-kpis">
            <x-ui.stat label="Package purchases (USDT)" :value="\App\Support\MoneyDisplay::usdt($metrics['packagePurchases']['USDT'])" />
            <x-ui.stat label="Package purchases (USD)" :value="\App\Support\MoneyDisplay::format($metrics['packagePurchases']['USD'], 'USD')" />
            <x-ui.stat label="Total Investment" :value="\App\Support\MoneyDisplay::usdt($metrics['totalInvestment'])" />
            <x-ui.stat label="Active Investment" :value="\App\Support\MoneyDisplay::usdt($metrics['activeInvestment'])" />
            <x-ui.stat label="Remaining Balance" :value="\App\Support\MoneyDisplay::usdt($metrics['remainingBalance'])" />
            <x-ui.stat label="Total Withdrawal" :value="\App\Support\MoneyDisplay::usdt($metrics['totalWithdrawal'])" />
        </div>
    </section>

    <section id="reports">
        <x-ui.section title="Rank Summary" lead="This is the saved rank record. It is separate from the 20-level poster schedule, which is not running." />
        <dl class="ds-detail">
            <div><dt>Current Rank</dt><dd>{{ $metrics['rankSummary']['current_rank']?->name ?? 'Unranked' }}</dd></div>
            <div><dt>Full Team Business</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['rankSummary']['full_team_business']) }}</dd></div>
            <div><dt>Levels Unlocked</dt><dd>{{ $metrics['rankSummary']['unlocked_levels'] }} / 32</dd></div>
            <div><dt>Next Rank</dt><dd>{{ $metrics['rankSummary']['next_rank']?->name ?? 'Maximum Rank Achieved' }}</dd></div>
            <div><dt>Remaining Business</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['rankSummary']['remaining_business']) }}</dd></div>
        </dl>
        <p><strong>Team Active Investment:</strong> {{ \App\Support\MoneyDisplay::usdt($metrics['teamActiveInvestment']) }}</p>
    </section>

    <section>
        <x-ui.section title="Recent transactions" lead="Purchase requests already saved for this member." />
        @if ($recentPurchases->isEmpty())
            <x-ui.empty title="No transactions recorded yet" message="Package requests and stored income will appear here. This page does not create them." />
        @else
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPurchases as $purchase)
                            <tr>
                                <td>{{ $purchase->reference }}</td>
                                <td>{{ $purchase->package_name }}</td>
                                <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination ?: 'USDT') }}</td>
                                <td><x-ui.badge :tone="$purchase->status === 'approved' ? 'success' : ($purchase->status === 'rejected' ? 'danger' : 'warning')">{{ ucfirst($purchase->status) }}</x-ui.badge></td>
                                <td>{{ $purchase->submitted_at?->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section>
        <x-ui.section title="Important announcements" />
        <div class="ds-dash-columns">
            <x-ui.card title="Income">
                <p>{{ $accountActive ? 'ROI and level income appear only after they have been calculated and stored. Opening this dashboard does not create a credit.' : 'ROI and level income are not credited when you open the dashboard. An inactive account cannot receive those package-dependent credits.' }}</p>
            </x-ui.card>
            <x-ui.card title="Withdrawals">
                <p>Withdrawal submissions stay closed. The poster lists a $20 minimum, ROI withdrawals from the 1st to the 5th, and working-wallet withdrawals on Mondays. Those rules are not enabled.</p>
            </x-ui.card>
            <x-ui.card title="Rewards">
                <p>Reward campaigns use approved USDT team business. A gift is confirmed only after it is selected.</p>
                <p><a href="{{ route('member.rewards.index') }}">My Rewards</a></p>
            </x-ui.card>
        </div>
    </section>
</div>
@endsection
