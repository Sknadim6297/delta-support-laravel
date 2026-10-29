@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')
@section('content-class', 'lh-native')

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
            default => 'is-muted',
        };
    };
    $kpis = [
        ['Total Members', $memberCount, 'All member records', 'ri-group-line', 'lh-card-1'],
        ['Active Members', $activeMembers, 'Status is active', 'ri-user-follow-line', 'lh-card-2'],
        ['Inactive Members', $inactiveMembers, 'Status is inactive', 'ri-user-unfollow-line', 'lh-card-3'],
        ['Total Packages', $packageCount, $activePackageCount.' active', 'ri-archive-line', 'lh-card-4'],
        ['Pending Payments', $pendingCount, 'Awaiting review', 'ri-time-line', 'lh-card-1'],
        ['Approved Payments', $approvedCount, 'Verified purchases', 'ri-checkbox-circle-line', 'lh-card-2'],
    ];
@endphp

@section('content')
<div class="row">
    @foreach ($kpis as [$label, $value, $hint, $icon, $tone])
        <div class="col-xl-4 col-md-6">
            <div class="lh-card {{ $tone }}">
                <div class="lh-card-content label-card">
                    <div class="title">
                        <div class="growth-numbers">
                            <h4>{{ $label }}</h4>
                            <h5>{{ $value }}</h5>
                        </div>
                        <span class="icon"><i class="{{ $icon }}"></i></span>
                    </div>
                    <p class="card-groth"><span>{{ $hint }}</span></p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    @foreach ([
        ['Active Campaigns', $rewardActive, route('admin.rewards.index'), 'ri-gift-line'],
        ['Qualified Members', $rewardQualified, route('admin.rewards.eligible'), 'ri-medal-line'],
        ['Pending Fulfillment', $rewardPending, route('admin.rewards.eligible', ['status' => 'selected']), 'ri-time-line'],
        ['Dispatched Gifts', $rewardDispatched, route('admin.rewards.reports'), 'ri-truck-line'],
        ['Delivered Gifts', $rewardDelivered, route('admin.rewards.reports'), 'ri-checkbox-circle-line'],
    ] as [$label, $value, $href, $icon])
        <div class="col-xl-4 col-md-6">
            <a href="{{ $href }}">
                <div class="lh-card lh-card-1">
                    <div class="lh-card-content label-card">
                        <div class="title">
                            <div class="growth-numbers">
                                <h4>{{ $label }}</h4>
                                <h5>{{ $value }}</h5>
                            </div>
                            <span class="icon"><i class="{{ $icon }}"></i></span>
                        </div>
                        <p class="card-groth"><span>Reward records</span></p>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-xl-4 col-md-6">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">USDT payments</h4>
            </div>
            <div class="lh-card-content">
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Approved</label></div><p>{{ \App\Support\MoneyDisplay::usdt($approvedPurchases['USDT']) }}</p></div>
                </div>
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Pending</label></div><p>{{ \App\Support\MoneyDisplay::usdt($pendingPurchases['USDT']) }}</p></div>
                </div>
                <p class="lh-note">New package sales. Not added to historical USD.</p>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">Historical USD</h4>
            </div>
            <div class="lh-card-content">
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Approved</label></div><p>{{ \App\Support\MoneyDisplay::format($approvedPurchases['USD'], 'USD') }}</p></div>
                </div>
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Pending</label></div><p>{{ \App\Support\MoneyDisplay::format($pendingPurchases['USD'], 'USD') }}</p></div>
                </div>
                <p class="lh-note">Older purchases only. Not added to USDT.</p>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-12">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">Stored wallets</h4>
            </div>
            <div class="lh-card-content">
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Activation Wallet</label></div><p>{{ \App\Support\MoneyDisplay::usdt($activationWalletTotal) }}</p></div>
                </div>
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>Working Wallet</label></div><p>{{ \App\Support\MoneyDisplay::usdt($workingWalletTotal) }}</p></div>
                </div>
                <div class="lh-detail-list">
                    <div class="lh-label"><div><label>ROI Wallet</label></div><p>{{ \App\Support\MoneyDisplay::usdt($roiWalletTotal) }}</p></div>
                </div>
                <p class="lh-note">These are stored balances, not sales revenue. ROI, level income, and withdrawals are not being credited.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">Record mix</h4>
            </div>
            <div class="lh-card-content">
                @if ($memberTotal === 0 && $paymentTotal === 0)
                    <p class="lh-note">Member and payment counts will appear here as real records are created.</p>
                @else
                    <div class="lh-detail-list">
                        <div class="lh-label"><div><label>Active members</label></div><p>{{ $activeMembers }} · {{ $activeShare }}%</p></div>
                        <div class="progress"><div class="progress-bar bg-primary" role="progressbar" style="width: {{ $activeShare }}%" aria-valuenow="{{ $activeShare }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                    <div class="lh-detail-list">
                        <div class="lh-label"><div><label>Inactive members</label></div><p>{{ $inactiveMembers }} · {{ $inactiveShare }}%</p></div>
                        <div class="progress"><div class="progress-bar bg-secondary" role="progressbar" style="width: {{ $inactiveShare }}%" aria-valuenow="{{ $inactiveShare }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                    <div class="lh-detail-list">
                        <div class="lh-label"><div><label>Approved payments</label></div><p>{{ $approvedCount }} · {{ $paymentShare((int) $approvedCount) }}%</p></div>
                        <div class="progress"><div class="progress-bar bg-primary" role="progressbar" style="width: {{ $paymentShare((int) $approvedCount) }}%" aria-valuenow="{{ $paymentShare((int) $approvedCount) }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                    <div class="lh-detail-list">
                        <div class="lh-label"><div><label>Pending payments</label></div><p>{{ $pendingCount }} · {{ $paymentShare((int) $pendingCount) }}%</p></div>
                        <div class="progress"><div class="progress-bar bg-secondary" role="progressbar" style="width: {{ $paymentShare((int) $pendingCount) }}%" aria-valuenow="{{ $paymentShare((int) $pendingCount) }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                    <div class="lh-detail-list">
                        <div class="lh-label"><div><label>Rejected payments</label></div><p>{{ $rejectedCount }} · {{ $paymentShare((int) $rejectedCount) }}%</p></div>
                        <div class="progress"><div class="progress-bar bg-secondary" role="progressbar" style="width: {{ $paymentShare((int) $rejectedCount) }}%" aria-valuenow="{{ $paymentShare((int) $rejectedCount) }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="row">
            <div class="col-12"><a class="lh-shortcut" href="{{ route('admin.members.registration') }}"><i class="ri-user-add-line"></i><span>Add Member</span></a></div>
            <div class="col-12"><a class="lh-shortcut" href="{{ route('admin.packages.create') }}"><i class="ri-add-box-line"></i><span>Add Package</span></a></div>
            <div class="col-12"><a class="lh-shortcut" href="{{ route('admin.payments.index', ['status' => 'pending']) }}"><i class="ri-secure-payment-line"></i><span>Review Payments</span></a></div>
            <div class="col-12"><a class="lh-shortcut" href="{{ route('admin.catalog-reports.show', ['report' => 'members']) }}"><i class="ri-bar-chart-2-line"></i><span>View Reports</span></a></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">Demonstration members</h4>
            </div>
            <div class="lh-card-content lh-scroll">
                @if ($demoMembers === [])
                    <p class="lh-note">No demonstration members are stored. Real member and payment totals are unchanged.</p>
                @else
                    <p class="lh-note">These 20 records are marked local-test. They are excluded from wallet credits, withdrawals, rewards, and financial totals. <a href="{{ route('admin.demo-income') }}">Open the income preview</a> or <a href="{{ route('admin.genealogy.tree-view') }}">the referral tree</a>.</p>
                    <table class="lh-data">
                        <thead><tr><th>Member</th><th>Sponsor</th><th>Package</th><th>Directs</th><th>Levels</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($demoMembers as $demo)
                            <tr>
                                <td>{{ $demo['name'] }}<br><small>{{ $demo['member_id'] }}</small></td>
                                <td>{{ $demo['sponsor_name'] }}<br><small>{{ $demo['sponsor_id'] }}</small></td>
                                <td>{{ $demo['package'] === '' ? 'None' : $demo['package'] }}</td>
                                <td>{{ $demo['qualifying_directs'] }}</td>
                                <td>{{ $demo['unlocked_levels'] }}</td>
                                <td><span class="lh-status {{ $statusClass((string) $demo['status']) }}">{{ ucfirst((string) $demo['status']) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6">
        <div class="lh-card">
            <div class="lh-card-header"><h4 class="lh-card-title">Recent registrations</h4></div>
            <div class="lh-card-content lh-scroll">
                @if ($recentMembers->isEmpty())
                    <p class="lh-note">No members yet.</p>
                @else
                    <table class="lh-data">
                        <thead><tr><th>Member</th><th>Status</th><th>Registered</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentMembers as $member)
                            <tr>
                                <td>{{ $member->member_name }}<br><small>{{ $member->member_id }}</small></td>
                                <td><span class="lh-status {{ $statusClass((string) $member->status) }}">{{ ucfirst((string) $member->status) }}</span></td>
                                <td>{{ $member->created_at?->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                                <td><a class="lh-primary-btn" href="{{ route('admin.members.update', ['member_id' => $member->member_id]) }}">View</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="lh-card">
            <div class="lh-card-header"><h4 class="lh-card-title">Recent purchases</h4></div>
            <div class="lh-card-content lh-scroll">
                @if ($recentPurchases->isEmpty())
                    <p class="lh-note">No purchases yet.</p>
                @else
                    <table class="lh-data">
                        <thead><tr><th>Package</th><th>Member</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentPurchases as $purchase)
                            <tr>
                                <td>{{ $purchase->package_name }}</td>
                                <td>{{ $purchase->member_id }}</td>
                                <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                                <td><span class="lh-status {{ $statusClass((string) $purchase->status) }}">{{ ucfirst((string) $purchase->status) }}</span></td>
                                <td>{{ $purchase->created_at?->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                                <td><a class="lh-primary-btn" href="{{ route('admin.payments.show', $purchase) }}">Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="lh-card">
            <div class="lh-card-header"><h4 class="lh-card-title">Recent payment reviews</h4></div>
            <div class="lh-card-content lh-scroll">
                @if ($recentReviews->isEmpty())
                    <p class="lh-note">No reviews yet. Approved and rejected payments appear after an administrator reviews them.</p>
                @else
                    <table class="lh-data">
                        <thead><tr><th>Reference</th><th>Member</th><th>Amount</th><th>Status</th><th>Reviewed</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($recentReviews as $purchase)
                            <tr>
                                <td>{{ $purchase->reference }}</td>
                                <td>{{ $purchase->member_id }}</td>
                                <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                                <td><span class="lh-status {{ $statusClass((string) $purchase->status) }}">{{ ucfirst((string) $purchase->status) }}</span></td>
                                <td>{{ $purchase->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                                <td><a class="lh-primary-btn" href="{{ route('admin.payments.show', $purchase) }}">Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
        @endif
            </div>
        </div>
    </div>
    </div>
@endsection
