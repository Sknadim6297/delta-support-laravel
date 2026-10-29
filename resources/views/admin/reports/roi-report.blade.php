@extends('admin.layouts.app')

@section('title', 'ROI Report')
@section('page-title', 'ROI Report')
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Stored ROI rows. This page does not calculate or credit a wallet." />

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total of the rows matching the current filters.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-green" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search stored ROI rows by member and date.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.report.roi-report') }}" id="roiReportForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <label>From Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <button class="ds-report-btn ds-report-btn-teal" type="button" onclick="window.location.href='{{ route('admin.report.roi-report.export') }}?' + new URLSearchParams(new FormData(document.getElementById('roiReportForm'))).toString()">Export to Excel</button>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.report.roi-report') }}'">Reset</button>
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
                        <th>Name</th>
                        <th>Form Investment ID</th>
                        <th>Income Amount (USDT)</th>
                        <th>On Investment Amount (USDT)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaction->member_id }}</td>
                            <td>{{ $transaction->member_name }}</td>
                            <td>{{ $transaction->investment_id }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->income_amount) }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->on_amount) }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No ROI transactions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $transactions->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
