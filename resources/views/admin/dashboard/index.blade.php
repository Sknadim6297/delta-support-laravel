@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')

@php
    $memberTotal = (int) $memberCount;
    $activeShare = $memberTotal > 0 ? (int) round(((int) $activeMembers / $memberTotal) * 100) : 0;
    $inactiveShare = $memberTotal > 0 ? max(0, 100 - $activeShare) : 0;
    $paymentTotal = (int) $pendingCount + (int) $approvedCount + (int) $rejectedCount;
    $paymentShare = function (int $count) use ($paymentTotal): int {
        return $paymentTotal > 0 ? (int) round(($count / $paymentTotal) * 100) : 0;
    };
    $statusClass = function (string $status): string {
        return match ($status) {
            'active', 'approved' => 'is-active',
            'pending' => 'is-pending',
            default => '',
        };
    };
    $kpis = [
        ['Total Members', $memberCount, 'All member records', 'ri-group-line', ''],
        ['Active Members', $activeMembers, 'Status is active', 'ri-user-follow-line', 'is-green'],
        ['Inactive Members', $inactiveMembers, 'Status is inactive', 'ri-user-unfollow-line', 'is-amber'],
        ['Total Packages', $packageCount, $activePackageCount.' active', 'ri-archive-line', 'is-teal'],
        ['Pending Payments', $pendingCount, 'Awaiting review', 'ri-time-line', 'is-orange'],
        ['Approved Payments', $approvedCount, 'Verified purchases', 'ri-checkbox-circle-line', 'is-green'],
    ];
@endphp

@section('content')
<div class="pv-dash">
    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Members and payments</h2>
                <p>Stored member and package counts.</p>
            </div>
        </div>
        <div class="pv-kpi-grid">
            @foreach ($kpis as [$label, $value, $hint, $icon, $tone])
                <article class="pv-kpi">
                    <div>
                        <p class="pv-kpi-label">{{ $label }}</p>
                        <p class="pv-kpi-value">{{ $value }}</p>
                        <p class="pv-kpi-hint">{{ $hint }}</p>
                    </div>
                    <span class="pv-kpi-icon {{ $tone }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Rewards</h2>
                <p>Campaign and gift records.</p>
            </div>
        </div>
        <div class="pv-kpi-grid">
            @foreach ([
                ['Active Campaigns', $rewardActive, route('admin.rewards.index'), 'ri-gift-line', 'is-violet'],
                ['Qualified Members', $rewardQualified, route('admin.rewards.eligible'), 'ri-medal-line', ''],
                ['Pending Fulfillment', $rewardPending, route('admin.rewards.eligible', ['status' => 'selected']), 'ri-time-line', 'is-orange'],
                ['Dispatched Gifts', $rewardDispatched, route('admin.rewards.reports'), 'ri-truck-line', 'is-teal'],
                ['Delivered Gifts', $rewardDelivered, route('admin.rewards.reports'), 'ri-checkbox-circle-line', 'is-green'],
            ] as [$label, $value, $href, $icon, $tone])
                <a class="pv-kpi" href="{{ $href }}">
                    <div>
                        <p class="pv-kpi-label">{{ $label }}</p>
                        <p class="pv-kpi-value">{{ $value }}</p>
                        <p class="pv-kpi-hint">Reward records</p>
                    </div>
                    <span class="pv-kpi-icon {{ $tone }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Money</h2>
                <p>USDT sales stay separate from historical USD. Wallet figures are stored balances.</p>
            </div>
        </div>
        <div class="pv-grid-3">
            <div class="pv-card">
                <h3 class="pv-card-title">USDT payments</h3>
                <div class="pv-metric-row"><span>Approved</span><strong>{{ \App\Support\MoneyDisplay::usdt($approvedPurchases['USDT']) }}</strong></div>
                <div class="pv-metric-row"><span>Pending</span><strong>{{ \App\Support\MoneyDisplay::usdt($pendingPurchases['USDT']) }}</strong></div>
                <p class="pv-note">New package sales. Not added to historical USD.</p>
            </div>
            <div class="pv-card">
                <h3 class="pv-card-title">Historical USD</h3>
                <div class="pv-metric-row"><span>Approved</span><strong>{{ \App\Support\MoneyDisplay::format($approvedPurchases['USD'], 'USD') }}</strong></div>
                <div class="pv-metric-row"><span>Pending</span><strong>{{ \App\Support\MoneyDisplay::format($pendingPurchases['USD'], 'USD') }}</strong></div>
                <p class="pv-note">Older purchases only. Not added to USDT.</p>
            </div>
            <div class="pv-card">
                <h3 class="pv-card-title">Stored wallets</h3>
                <div class="pv-metric-row"><span>Activation Wallet</span><strong>{{ \App\Support\MoneyDisplay::usdt($activationWalletTotal) }}</strong></div>
                <div class="pv-metric-row"><span>Working Wallet</span><strong>{{ \App\Support\MoneyDisplay::usdt($workingWalletTotal) }}</strong></div>
                <div class="pv-metric-row"><span>ROI Wallet</span><strong>{{ \App\Support\MoneyDisplay::usdt($roiWalletTotal) }}</strong></div>
                <p class="pv-note">Stored balances, not sales revenue.</p>
            </div>
        </div>
    </section>

    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Record mix</h2>
                <p>Share of members and payment decisions.</p>
            </div>
        </div>
        <div class="pv-grid-2">
            <div class="pv-card">
                <h3 class="pv-card-title">Record mix</h3>
                @if ($memberTotal === 0 && $paymentTotal === 0)
                    <p class="pv-note">Member and payment counts will appear here as real records are created.</p>
                @else
                    <div class="pv-metric-row"><span>Active members</span><strong>{{ $activeMembers }} · {{ $activeShare }}%</strong></div>
                    <div class="pv-progress"><span class="is-green" style="width: {{ $activeShare }}%"></span></div>
                    <div class="pv-metric-row"><span>Inactive members</span><strong>{{ $inactiveMembers }} · {{ $inactiveShare }}%</strong></div>
                    <div class="pv-progress"><span class="is-amber" style="width: {{ $inactiveShare }}%"></span></div>
                    <div class="pv-metric-row"><span>Approved payments</span><strong>{{ $approvedCount }} · {{ $paymentShare((int) $approvedCount) }}%</strong></div>
                    <div class="pv-progress"><span style="width: {{ $paymentShare((int) $approvedCount) }}%"></span></div>
                    <div class="pv-metric-row"><span>Pending payments</span><strong>{{ $pendingCount }} · {{ $paymentShare((int) $pendingCount) }}%</strong></div>
                    <div class="pv-progress"><span class="is-orange" style="width: {{ $paymentShare((int) $pendingCount) }}%"></span></div>
                    <div class="pv-metric-row"><span>Rejected payments</span><strong>{{ $rejectedCount }} · {{ $paymentShare((int) $rejectedCount) }}%</strong></div>
                    <div class="pv-progress"><span class="is-rose" style="width: {{ $paymentShare((int) $rejectedCount) }}%"></span></div>
                @endif
            </div>
            <div class="pv-actions" style="align-content: start;">
                <a class="pv-action" href="{{ route('admin.members.registration') }}"><i class="ri-user-add-line" aria-hidden="true"></i><span>Add Member</span></a>
                <a class="pv-action" href="{{ route('admin.packages.create') }}"><i class="ri-add-box-line" aria-hidden="true"></i><span>Add Package</span></a>
                <a class="pv-action" href="{{ route('admin.payments.index', ['status' => 'pending']) }}"><i class="ri-secure-payment-line" aria-hidden="true"></i><span>Review Payments</span></a>
                <a class="pv-action" href="{{ route('admin.catalog-reports.show', ['report' => 'members']) }}"><i class="ri-bar-chart-2-line" aria-hidden="true"></i><span>View Reports</span></a>
            </div>
        </div>
    </section>

    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Demonstration members</h2>
                <p>A separate tree. These rows stay out of wallet credits and payment totals.</p>
            </div>
        </div>
        <div class="pv-card">
            <h3 class="pv-card-title">Referral preview</h3>
            @if ($demoMembers === [])
                <div class="pv-empty">
                    <strong>No demonstration members</strong>
                    Real member and payment totals are unchanged.
                </div>
            @else
                <p class="pv-note">These records are marked local-test and excluded from wallet credits, withdrawals, rewards, and financial totals. <a class="pv-link" href="{{ route('admin.demo-income') }}">Open income preview</a> or <a class="pv-link" href="{{ route('admin.genealogy.tree-view') }}">referral tree</a>.</p>
                <div class="ds-table-wrap" style="border:0;box-shadow:none;margin-top:10px;">
                    <table class="pv-table">
                        <thead><tr><th>Member</th><th>Sponsor</th><th>Package</th><th>Directs</th><th>Levels</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($demoMembers as $demo)
                            <tr>
                                <td>{{ $demo['name'] }}<br><small>{{ $demo['member_id'] }}</small></td>
                                <td>{{ $demo['sponsor_name'] }}<br><small>{{ $demo['sponsor_id'] }}</small></td>
                                <td>{{ $demo['package'] === '' ? 'None' : $demo['package'] }}</td>
                                <td>{{ $demo['qualifying_directs'] }}</td>
                                <td>{{ $demo['unlocked_levels'] }}</td>
                                <td><span class="pv-status {{ $statusClass((string) $demo['status']) }}">{{ ucfirst((string) $demo['status']) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="pv-section">
        <div class="pv-section-head">
            <div>
                <h2>Recent activity</h2>
                <p>Newest registrations, purchases, and reviews.</p>
            </div>
        </div>
        <div class="pv-grid-3">
            <div class="pv-card">
                <h3 class="pv-card-title">Recent registrations</h3>
                @if ($recentMembers->isEmpty())
                    <div class="pv-empty"><strong>No members yet</strong></div>
                @else
                    <table class="pv-table">
                        <thead><tr><th>Member</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentMembers as $member)
                            <tr>
                                <td>{{ $member->member_name }}<br><small>{{ $member->member_id }}</small></td>
                                <td><span class="pv-status {{ $statusClass((string) $member->status) }}">{{ ucfirst((string) $member->status) }}</span></td>
                                <td><a class="pv-link" href="{{ route('admin.members.update', ['member_id' => $member->member_id]) }}">View</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="pv-card">
                <h3 class="pv-card-title">Recent purchases</h3>
                @if ($recentPurchases->isEmpty())
                    <div class="pv-empty"><strong>No purchases yet</strong></div>
                @else
                    <table class="pv-table">
                        <thead><tr><th>Package</th><th>Amount</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentPurchases as $purchase)
                            <tr>
                                <td>{{ $purchase->package_name }}<br><small>{{ $purchase->member_id }}</small></td>
                                <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                                <td><a class="pv-link" href="{{ route('admin.payments.show', $purchase) }}">Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="pv-card">
                <h3 class="pv-card-title">Recent payment reviews</h3>
                @if ($recentReviews->isEmpty())
                    <div class="pv-empty"><strong>No reviews yet</strong>Approved and rejected payments appear after review.</div>
                @else
                    <table class="pv-table">
                        <thead><tr><th>Reference</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentReviews as $purchase)
                            <tr>
                                <td>{{ $purchase->reference }}<br><small>{{ $purchase->member_id }}</small></td>
                                <td><span class="pv-status {{ $statusClass((string) $purchase->status) }}">{{ ucfirst((string) $purchase->status) }}</span></td>
                                <td><a class="pv-link" href="{{ route('admin.payments.show', $purchase) }}">Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
