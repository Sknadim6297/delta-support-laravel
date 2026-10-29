@extends('admin.layouts.app')

@section('title', 'Level Commission Report')
@section('page-title', 'Level Commission Report')
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Stored level commission rows. This page does not calculate or credit a wallet." />

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total of the rows matching the current filters.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-violet" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount ?? 0)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search stored commission rows by member, date, and level.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.report.level-income') }}" id="levelIncomeReportForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <label>From Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <label>Level
                    <select name="level">
                        <option value="">All Levels</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level }}" @selected((string) request('level') === (string) $level)>Level {{ $level }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="ds-report-btn ds-report-btn-teal" type="button" onclick="window.location.href='{{ route('admin.report.level-income.export') }}?' + new URLSearchParams(new FormData(document.getElementById('levelIncomeReportForm'))).toString()">Export to Excel</button>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.report.level-income') }}'">Reset</button>
            </form>
        </div>
    </section>

    @isset($levelAccess)
    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Level Commission Access</h2>
            <p>{{ $rankData['current_rank']?->name ?? 'Unranked' }} · {{ $rankData['unlocked_levels'] ?? 0 }} / 32 levels unlocked</p>
        </header>
        <div class="ds-report-card">
            <div class="ds-level-row">
                @foreach ($levelAccess as $access)
                    <span class="ds-level-chip {{ $access['unlocked'] ? 'level-unlocked' : 'level-locked' }}">L{{ $access['level'] }}</span>
                @endforeach
            </div>
            <p class="ds-report-note">Locked levels are not eligible for Level Commission.</p>
        </div>
    </section>
    @endisset

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Records</h2>
        </header>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Serial No</th>
                        <th>Reference</th>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Income Amount (USDT)</th>
                        <th>On Amount (USDT)</th>
                        <th>From Member ID</th>
                        <th>From Level</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaction->reference }}</td>
                            <td>{{ $transaction->member_id }}</td>
                            <td>{{ $transaction->member_name }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->income_amount) }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->on_amount) }}</td>
                            <td>{{ $transaction->from_member_id }}</td>
                            <td>{{ $transaction->level }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No level commission transactions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $transactions->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
