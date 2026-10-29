@extends('admin.layouts.app')

@section('title', 'Rank Achievement Report')
@section('page-title', 'Rank Achievement Report')
@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Stored rank achievements. This page does not award a rank or credit a wallet." />

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Summary</h2>
            <p>Total amount stored with the current filters.</p>
        </header>
        <div class="ds-stat-grid">
            <x-ui.stat class="ds-tone-amber" label="Total Amount (USDT)" :value="\App\Support\MoneyDisplay::usdt($totalAmount)" hint="USDT only" />
        </div>
    </section>

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Filters</h2>
            <p>Search stored achievements by member, rank, and date.</p>
        </header>
        <div class="ds-report-card">
            <form method="GET" action="{{ route('admin.report.rank-achievement') }}" id="rankAchievementReportForm" class="ds-filter-form">
                <label>Member ID
                    <input type="text" name="member_id" value="{{ request('member_id') }}" placeholder="Enter Member ID">
                </label>
                <label>Rank
                    <select name="rank_id">
                        <option value="">All Rank</option>
                        @foreach ($ranks as $rank)
                            <option value="{{ $rank->id }}" @selected((string) request('rank_id') === (string) $rank->id)>{{ $rank->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>From Date
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label>To Date
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <button class="ds-report-btn ds-report-btn-blue" type="submit">Search</button>
                <button class="ds-report-btn ds-report-btn-amber" type="button" onclick="window.location.href='{{ route('admin.report.rank-achievement') }}'">Reset</button>
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
                        <th>Achieved Rank</th>
                        <th>Achieving Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($achievements as $achievement)
                        <tr>
                            <td>{{ $achievements->firstItem() + $loop->index }}</td>
                            <td>{{ $achievement->member_id }}</td>
                            <td>{{ $achievement->member_name }}</td>
                            <td>{{ $achievement->rank?->name ?? 'N/A' }}</td>
                            <td>{{ $achievement->achieved_at?->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No rank achievements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $achievements->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
