@extends('member.layouts.app')

@section('title', 'Closed Investments')
@section('page-title', 'Closed Investments')
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Your closed investments matching the current filters." />

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total closing amount for filtered rows.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-amber" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Filter by close date.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('member.investments.closed') }}" id="closedInvestmentForm" class="ds-filter-form">
                <label>From Close Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Close Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('member.investments.closed') }}'">Reset</button>
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
                        <th>Category</th>
                        <th>Investment Id</th>
                        <th>Investment Amount (USDT)</th>
                        <th>Closing Amount (USDT)</th>
                        <th>Investment Date</th>
                        <th>Close Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($investments as $investment)
                        <tr>
                            <td>{{ $investments->firstItem() + $loop->index }}</td>
                            <td>Closed</td>
                            <td>{{ $investment->investment_id }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($investment->amount) }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($investment->closing_amount) }}</td>
                            <td>{{ $investment->created_at?->format('d-m-Y') }}</td>
                            <td>{{ $investment->closed_at?->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No Record</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $investments->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection