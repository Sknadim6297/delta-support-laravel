@extends('admin.layouts.app')

@section('title', 'Activation Credits')
@section('page-title', 'Activation Credits')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Stored activation wallet credit transfers." />

    @if (isset($errors) && $errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total transferred amount for the current filters.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-green" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search credit transfers by member and transfer date.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.activation-wallet.credit-entry.list') }}" id="activationWalletListForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <label>From Transfer Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Transfer Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <button class="ds-report-btn ds-report-btn-teal" type="button" onclick="window.location.href='{{ route('admin.activation-wallet.credit-entry.export') }}?' + new URLSearchParams(new FormData(document.getElementById('activationWalletListForm'))).toString()">Export to Excel</button>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.activation-wallet.credit-entry.list') }}'">Reset</button>
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
                        <th>To Member ID</th>
                        <th>To Member Name</th>
                        <th>Amount (USDT)</th>
                        <th>Transfer Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transactions->firstItem() + $loop->index }}</td>
                            <td>{{ $transaction->member_id }}</td>
                            <td>{{ $transaction->member_name }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->amount) }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No activation wallet transfers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $transactions->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection