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
    $toneFor = function (string $status): string {
        return match ($status) {
            'active', 'approved' => 'success',
            'pending' => 'warning',
            'rejected', 'inactive' => 'muted',
            default => 'brand',
        };
    };
@endphp

@section('content')
<div class="ds-dash">
    <x-ui.page-header
        title="Dashboard"
        kicker="Admin"
        lead="Live member, package, and payment totals. USDT sales stay separate from historical USD purchases. ROI, level income, and withdrawals are not being credited."
    />

    <section class="ds-dash-block" aria-labelledby="dash-overview">
        <x-ui.section id="dash-overview" title="Overview" lead="Counts from the current database." />
        <div class="ds-dash-kpis">
            <x-ui.stat label="Total members" :value="$memberCount" hint="All member records">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </x-slot:icon>
            </x-ui.stat>
            <x-ui.stat label="Active members" :value="$activeMembers" hint="Status is active">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
                </x-slot:icon>
            </x-ui.stat>
            <x-ui.stat label="Inactive members" :value="$inactiveMembers" hint="Status is inactive">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 8 5 5"/><path d="m22 8-5 5"/></svg>
                </x-slot:icon>
            </x-ui.stat>
            <x-ui.stat label="Total packages" :value="$packageCount" :hint="$activePackageCount.' active'">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                </x-slot:icon>
            </x-ui.stat>
            <x-ui.stat label="Pending payments" :value="$pendingCount" hint="Awaiting review">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </x-slot:icon>
            </x-ui.stat>
            <x-ui.stat label="Approved payments" :value="$approvedCount" hint="Verified purchases">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                </x-slot:icon>
            </x-ui.stat>
        </div>
    </section>

    <section class="ds-dash-block" aria-labelledby="dash-money">
        <x-ui.section id="dash-money" title="Financial summary" lead="USDT and historical USD are stored separately and are never added together." />
        <div class="ds-dash-money">
            <x-ui.card title="USDT" subtitle="New package sales">
                <dl class="ds-detail">
                    <x-ui.detail label="Approved">{{ \App\Support\MoneyDisplay::usdt($approvedPurchases['USDT']) }}</x-ui.detail>
                    <x-ui.detail label="Pending">{{ \App\Support\MoneyDisplay::usdt($pendingPurchases['USDT']) }}</x-ui.detail>
                </dl>
            </x-ui.card>
            <x-ui.card title="USD" subtitle="Historical purchases only">
                <dl class="ds-detail">
                    <x-ui.detail label="Approved">{{ \App\Support\MoneyDisplay::format($approvedPurchases['USD'], 'USD') }}</x-ui.detail>
                    <x-ui.detail label="Pending">{{ \App\Support\MoneyDisplay::format($pendingPurchases['USD'], 'USD') }}</x-ui.detail>
                </dl>
            </x-ui.card>
        </div>
        <div class="ds-dash-wallets">
            <x-ui.stat label="Activation wallet" :value="\App\Support\MoneyDisplay::usdt($activationWalletTotal)" hint="Stored USDT balance" />
            <x-ui.stat label="Working wallet" :value="\App\Support\MoneyDisplay::usdt($workingWalletTotal)" hint="Stored USDT balance" />
            <x-ui.stat label="ROI wallet" :value="\App\Support\MoneyDisplay::usdt($roiWalletTotal)" hint="Stored balance. No ROI is being generated." />
        </div>
    </section>

    <div class="ds-dash-columns">
        <section class="ds-dash-block" aria-labelledby="dash-mix">
            <x-ui.card title="Record mix" subtitle="No time-series chart is stored. These bars use the live counts above.">
                @if ($memberTotal === 0 && $paymentTotal === 0)
                    <x-ui.empty title="Nothing to chart yet" message="Member and payment counts will appear here as real records are created." />
                @else
                    <div class="ds-mix">
                        <p class="ds-mix-title">Members</p>
                        <div class="ds-mix-row">
                            <span>Active</span>
                            <span class="ds-mix-track" aria-hidden="true"><span class="ds-mix-fill is-active" style="width: {{ $activeShare }}%"></span></span>
                            <strong>{{ $activeMembers }}</strong>
                        </div>
                        <div class="ds-mix-row">
                            <span>Inactive</span>
                            <span class="ds-mix-track" aria-hidden="true"><span class="ds-mix-fill is-muted" style="width: {{ $inactiveShare }}%"></span></span>
                            <strong>{{ $inactiveMembers }}</strong>
                        </div>
                        <p class="ds-mix-title">Payments</p>
                        <div class="ds-mix-row">
                            <span>Approved</span>
                            <span class="ds-mix-track" aria-hidden="true"><span class="ds-mix-fill is-active" style="width: {{ $paymentShare((int) $approvedCount) }}%"></span></span>
                            <strong>{{ $approvedCount }}</strong>
                        </div>
                        <div class="ds-mix-row">
                            <span>Pending</span>
                            <span class="ds-mix-track" aria-hidden="true"><span class="ds-mix-fill is-pending" style="width: {{ $paymentShare((int) $pendingCount) }}%"></span></span>
                            <strong>{{ $pendingCount }}</strong>
                        </div>
                        <div class="ds-mix-row">
                            <span>Rejected</span>
                            <span class="ds-mix-track" aria-hidden="true"><span class="ds-mix-fill is-muted" style="width: {{ $paymentShare((int) $rejectedCount) }}%"></span></span>
                            <strong>{{ $rejectedCount }}</strong>
                        </div>
                    </div>
                @endif
            </x-ui.card>
        </section>

        <section class="ds-dash-block" aria-labelledby="dash-actions">
            <x-ui.card title="Quick actions" subtitle="Each link opens an existing admin route.">
                <div class="ds-dash-actions">
                    <a class="ds-dash-action" href="{{ route('admin.members.registration') }}">
                        <span class="ds-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></span>
                        <span><strong>Add Member</strong><small>Open registration</small></span>
                    </a>
                    <a class="ds-dash-action" href="{{ route('admin.packages.create') }}">
                        <span class="ds-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
                        <span><strong>Add Package</strong><small>Create a package</small></span>
                    </a>
                    <a class="ds-dash-action" href="{{ route('admin.payments.index', ['status' => 'pending']) }}">
                        <span class="ds-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg></span>
                        <span><strong>Review Payments</strong><small>Pending queue</small></span>
                    </a>
                    <a class="ds-dash-action" href="{{ route('admin.catalog-reports.show', ['report' => 'members']) }}">
                        <span class="ds-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></span>
                        <span><strong>View Reports</strong><small>Member report</small></span>
                    </a>
                </div>
            </x-ui.card>
        </section>
    </div>

    <section class="ds-dash-block" aria-labelledby="dash-activity">
        <x-ui.section id="dash-activity" title="Recent activity" lead="Latest stored records. Amounts keep the denomination saved on each purchase." />
        <div class="ds-dash-columns">
            <x-ui.card title="Recent registrations">
                @if ($recentMembers->isEmpty())
                    <x-ui.empty title="No members yet" message="Registration remains available. New members will be listed here." />
                @else
                    <div class="ds-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentMembers as $member)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.members.update', ['member_id' => $member->member_id]) }}">{{ $member->member_name }}</a>
                                            <small class="ds-cell-meta">{{ $member->member_id }}</small>
                                        </td>
                                        <td><x-ui.badge :tone="$toneFor((string) $member->status)">{{ ucfirst((string) $member->status) }}</x-ui.badge></td>
                                        <td>{{ $member->created_at?->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
            <x-ui.card title="Recent purchases">
                @if ($recentPurchases->isEmpty())
                    <x-ui.empty title="No purchases yet" message="Package purchases will appear here after a member submits one." />
                @else
                    <div class="ds-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Purchase</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPurchases as $purchase)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.payments.show', $purchase) }}">{{ $purchase->package_name }}</a>
                                            <small class="ds-cell-meta">{{ $purchase->member_id }}</small>
                                        </td>
                                        <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                                        <td><x-ui.badge :tone="$toneFor((string) $purchase->status)">{{ ucfirst((string) $purchase->status) }}</x-ui.badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
        <x-ui.card title="Recent payment reviews" subtitle="Purchases that already have a review time.">
            @if ($recentReviews->isEmpty())
                <x-ui.empty title="No reviews yet" message="Approved and rejected payments will be listed here after an administrator reviews them." />
            @else
                <div class="ds-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Reviewed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentReviews as $purchase)
                                <tr>
                                    <td><a href="{{ route('admin.payments.show', $purchase) }}">{{ $purchase->reference }}</a></td>
                                    <td>{{ $purchase->member_id }}</td>
                                    <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                                    <td><x-ui.badge :tone="$toneFor((string) $purchase->status)">{{ ucfirst((string) $purchase->status) }}</x-ui.badge></td>
                                    <td>{{ $purchase->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </section>
</div>
@endsection
