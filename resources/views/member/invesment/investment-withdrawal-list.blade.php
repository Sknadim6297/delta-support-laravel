@extends('admin.layouts.app')

@section('title', 'Withdrawn Investments')
@section('page-title', 'Withdrawn Investments')
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Investment withdrawal requests and completed withdrawals." />

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total withdrawn amount for the current filters.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-violet" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search withdrawals by member and withdraw date.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.investments.investment-withdrawal-list') }}" id="investmentWithdrawalListForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <label>From Withdraw Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Withdraw Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.investments.investment-withdrawal-list') }}'">Reset</button>
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
                        <th>Investment Id</th>
                        <th>Withdrawal Amount (USDT)</th>
                        <th>Investment Date</th>
                        <th>Withdraw Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $withdrawal)
                        <tr>
                            <td>{{ $withdrawals->firstItem() + $loop->index }}</td>
                            <td>{{ $withdrawal->member_id }}</td>
                            <td>{{ $withdrawal->member_name }}</td>
                            <td>{{ $withdrawal->investment_id }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($withdrawal->withdrawal_amount) }}</td>
                            <td>{{ $withdrawal->investment?->created_at?->format('d-m-Y') }}</td>
                            <td>{{ $withdrawal->withdrawn_at?->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No investment withdrawals found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection