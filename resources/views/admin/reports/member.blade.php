@extends('admin.layouts.app')
@section('title', $member->member_id.' Report')
@section('page-title', $member->member_name)
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Member activity and linked purchase history. Passwords and wallet credentials are not shown.">
        <x-slot:actions>
            <a class="ds-btn" href="{{ route('admin.catalog-reports.show', ['report' => 'members']) }}">Back to member reports</a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Member record</h2>
            <p>Account details stored for this member.</p>
        </header>
    <div class="ds-table-wrap">
        <table>
            <tbody>
                <tr><th>Member ID</th><td>{{ $member->member_id }}</td></tr>
                <tr><th>Full name</th><td>{{ $member->member_name }} @if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid'))<x-ui.badge tone="warning">Local test</x-ui.badge>@endif</td></tr>
                <tr><th>Email</th><td>{{ $member->email }}</td></tr>
                <tr><th>Mobile</th><td>{{ $member->mobile_no }}</td></tr>
                <tr><th>Sponsor</th><td>{{ $member->sponsor_id }} · {{ $member->sponsor_name }}</td></tr>
                <tr><th>Registered</th><td>{{ $member->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td></tr>
                <tr><th>Account status</th><td>{{ ucfirst($member->status) }}</td></tr>
                <tr><th>Last login</th><td>Not tracked</td></tr>
                <tr><th>Activation date</th><td>Not tracked</td></tr>
                <tr><th>Activated by</th><td>Not tracked</td></tr>
            </tbody>
        </table>
    </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Wallet and fund summary</h2>
            <p>USDT balances are read from stored member wallet columns. Reserved funds are excluded from available totals.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat label="Total stored" :value="\App\Support\MoneyDisplay::usdt($fundSummary['total']['stored'])" hint="All existing USDT wallets" />
            <x-ui.stat label="Reserved" :value="\App\Support\MoneyDisplay::usdt($fundSummary['total']['reserved'])" hint="Pending or open reservations" />
            <x-ui.stat label="Available" :value="\App\Support\MoneyDisplay::usdt($fundSummary['total']['available'])" hint="Stored minus reserved" />
            <x-ui.stat label="Total withdrawn" :value="\App\Support\MoneyDisplay::usdt($fundSummary['total']['paid'])" hint="Gross settled withdrawals" />
        </div>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Wallet</th>
                        <th>Current balance</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>Total credited</th>
                        <th>Total debited</th>
                        <th>Withdrawn</th>
                    </tr>
                </thead>
                <tbody>
                @foreach (['roi' => 'ROI Wallet', 'working' => 'Working Wallet', 'activation' => 'Activation Wallet'] as $wallet => $label)
                    @php($row = $fundSummary['wallets'][$wallet])
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['stored']) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['reserved']) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['available']) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['credited']) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['debits']) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($row['paid']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Recent wallet transactions</h2>
            <p>Latest immutable ledger rows only. This does not recalculate or change balances.</p>
        </header>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Wallet</th>
                        <th>Direction</th>
                        <th>Amount</th>
                        <th>Opening</th>
                        <th>Closing</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($walletTransactions as $entry)
                    <tr>
                        <td>{{ $entry->reference }}</td>
                        <td>{{ ucfirst($entry->wallet) }}</td>
                        <td>{{ ucfirst($entry->direction) }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($entry->amount) }}</td>
                        <td>{{ $entry->opening_balance === null ? 'Not recorded' : \App\Support\MoneyDisplay::usdt($entry->opening_balance) }}</td>
                        <td>{{ $entry->closing_balance === null ? 'Not recorded' : \App\Support\MoneyDisplay::usdt($entry->closing_balance) }}</td>
                        <td>{{ $entry->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No wallet ledger transactions recorded for this member.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $walletTransactions->links('pagination::bootstrap-5') }}</div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Package business</h2>
            <p>Lifetime totals from eligible approved USDT package purchase snapshots. Each approved purchase is counted once; zero means no qualifying purchases are recorded.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat label="First Level Business" :value="\App\Support\MoneyDisplay::usdt($packageBusiness['first_level_business'])" hint="Direct referrals only" />
            <x-ui.stat label="Total Team Business" :value="\App\Support\MoneyDisplay::usdt($packageBusiness['total_team_business'])" hint="Member plus complete downline" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Purchase and payment history</h2>
            <p>Purchase amounts are shown in USDT.</p>
        </header>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Network</th>
                    <th>Transaction ID</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th>Reviewed by</th>
                    <th>Rejection reason</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->reference }}</td>
                    <td>{{ $purchase->package_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($purchase->price_usd) }}</td>
                    <td>{{ $purchase->network ?: 'Not stored' }}</td>
                    <td>{{ $purchase->payment_reference }}</td>
                    <td>{{ $purchase->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td><x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge></td>
                    <td>{{ $purchase->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?: 'Not stored' }}</td>
                    <td>{{ $purchase->reviewer->name ?? 'Not stored' }}</td>
                    <td>{{ $purchase->rejection_reason }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No purchase records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Recorded activity</h2>
        </header>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr><th>Action</th><th>Started</th><th>Ended</th><th>Admin</th></tr>
            </thead>
            <tbody>
            @forelse ($activity as $entry)
                <tr>
                    <td>{{ ucfirst($entry->action) }}</td>
                    <td>{{ $entry->started_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td>{{ $entry->ended_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?: 'Not stored' }}</td>
                    <td>{{ $entry->admin->name ?? 'Not stored' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No activity records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    </section>
</div>
@endsection
