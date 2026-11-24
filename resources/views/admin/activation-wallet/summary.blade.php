@extends('admin.layouts.app')

@section('title', 'Activation Wallet')
@section('page-title', 'Activation Wallet')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Activation wallet balances for members matching your filters." />

    @if (isset($errors) && $errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total activation wallet balance for the filtered members.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-teal" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search by member ID.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.activation-wallet.summary') }}" id="activationWalletSummaryForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <button class="ds-report-btn ds-report-btn-teal" type="button" onclick="window.location.href='{{ route('admin.activation-wallet.summary.export') }}?' + new URLSearchParams(new FormData(document.getElementById('activationWalletSummaryForm'))).toString()">Export to Excel</button>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.activation-wallet.summary') }}'">Reset</button>
            </form>
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Records</h2>
        </header>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Serial No</th>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Act. Wallet Balance (USDT)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>{{ $members->firstItem() + $loop->index }}</td>
                            <td>{{ $member->member_id }}</td>
                            <td>{{ $member->member_name }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No activation wallet balances found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $members->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection