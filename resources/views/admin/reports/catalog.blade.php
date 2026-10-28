@extends('admin.layouts.app')
@section('title', ucfirst($report).' Reports')
@section('page-title', ucfirst($report).' Reports')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header :lead="$report === 'earnings'
        ? 'Stored records only. Income is not calculated on this page.'
        : 'Filtered records from the live database. Exports use the same filters.'" />

    @if ($errors->any())
        <x-ui.alert type="danger">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="ds-stat-grid">
        @foreach ($counts as $label => $value)
            <x-ui.stat :label="$label" :value="$value" />
        @endforeach
        @foreach ($totals as $total)
            <x-ui.stat :label="'Total '.$total['currency']" :value="$total['total']" hint="This currency only" />
        @endforeach
    </div>

    <form class="ds-filter-form" method="GET">
        @if ($report === 'packages')
            <label>Dataset
                <select name="dataset">
                    <option value="purchases" @selected(($filters['dataset'] ?? '') === 'purchases')>Purchase history</option>
                    <option value="catalog" @selected(($filters['dataset'] ?? '') === 'catalog')>Package catalog</option>
                </select>
            </label>
        @endif
        <label>Search
            <input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100">
        </label>
        <label>From
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label>To
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
        <label>Status
            <select name="status">
                <option value="">Any</option>
                @foreach (match ($report) {
                    'members' => ['active' => 'Active', 'inactive' => 'Inactive'],
                    'payments' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
                    'earnings' => ['generated' => 'Generated'],
                    default => ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
                } as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Member ID
            <input name="member_id" value="{{ $filters['member_id'] ?? '' }}" maxlength="32">
        </label>
        <label>Package
            <select name="package">
                <option value="">Any</option>
                @foreach ($packages as $package)
                    <option value="{{ $package->code }}" @selected(($filters['package'] ?? '') === $package->code)>{{ $package->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Currency
            <select name="currency">
                <option value="">Any</option>
                <option value="USD" @selected(($filters['currency'] ?? '') === 'USD')>USD</option>
                <option value="USDT" @selected(($filters['currency'] ?? '') === 'USDT')>USDT</option>
            </select>
        </label>
        @if ($report === 'earnings')
            <label>Income type
                <select name="income_type">
                    <option value="">Any stored type</option>
                    <option value="roi" @selected(($filters['income_type'] ?? '') === 'roi')>ROI income</option>
                    <option value="level" @selected(($filters['income_type'] ?? '') === 'level')>Level commission</option>
                    <option value="wallet" @selected(($filters['income_type'] ?? '') === 'wallet')>Wallet credit</option>
                </select>
            </label>
        @endif
        <label>Sort
            <select name="sort">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option>
            </select>
        </label>
        <button type="submit">Apply filters</button>
    </form>

    <form class="ds-filter-form" method="GET" action="{{ route('admin.catalog-reports.export', ['report' => $report]) }}">
        @foreach (request()->query() as $key => $value)
            @if (is_string($value) && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label>Format
            <select name="format">
                <option value="pdf">PDF</option>
                <option value="xlsx">Excel</option>
                <option value="csv">CSV</option>
            </select>
        </label>
        <button type="submit" name="scope" value="filtered">Export filtered records</button>
        <button type="submit" name="scope" value="full">Export full report</button>
    </form>

    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                    @if ($report === 'members')
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($headings as $heading)
                        <td>{{ $row[$heading] }}</td>
                    @endforeach
                    @if ($report === 'members')
                        <td><a href="{{ route('admin.catalog-reports.members.show', ['member' => $row['Member ID']]) }}">View details</a></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) + ($report === 'members' ? 1 : 0) }}">No records found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $rows->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
