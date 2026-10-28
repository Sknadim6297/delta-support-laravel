@extends('admin.layouts.app')
@section('title', 'Withdrawal Requests')
@section('page-title', 'Withdrawal Requests')
@php
    $filtered = request()->hasAny(['member_id', 'reference', 'status', 'from_date', 'to_date', 'network']);
@endphp
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        title="Withdrawal Requests"
        lead="Stored requests only. Approve, reject, complete, and transfer actions are not available."
    />
    <form class="ds-filter" method="GET">
        <label><span>Member ID</span><input name="member_id" value="{{ request('member_id') }}"></label>
        <label><span>Withdrawal reference</span><input name="reference" value="{{ request('reference') }}"></label>
        <label><span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\WithdrawalStatus::label($status) }}</option>
                @endforeach
            </select>
        </label>
        <label><span>From date</span><input type="date" name="from_date" value="{{ request('from_date') }}"></label>
        <label><span>To date</span><input type="date" name="to_date" value="{{ request('to_date') }}"></label>
        <label><span>Network</span><input name="network" value="{{ request('network') }}"></label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            @if ($filtered)
                <a class="ds-btn ds-btn-secondary" href="{{ route('admin.withdrawals.index') }}">Reset</a>
            @endif
        </div>
    </form>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member ID</th>
                    <th>Wallet</th>
                    <th class="ds-money">Gross amount</th>
                    <th class="ds-money">Charge</th>
                    <th class="ds-money">Net amount</th>
                    <th>Network</th>
                    <th>Status</th>
                    <th>Request date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr>
                    <td>{{ $withdrawal->reference }}</td>
                    <td>{{ $withdrawal->member_id }}</td>
                    <td>{{ $withdrawal->source_wallet }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</td>
                    <td class="ds-money">{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</td>
                    <td class="ds-money">{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->network ?: 'Not configured' }}</td>
                    <td>@include('partials.withdrawal-status', ['status' => $withdrawal->status])</td>
                    <td>{{ $withdrawal->created_at?->format('d M Y') }}</td>
                    <td><a class="ds-btn ds-btn-secondary ds-btn-sm" href="{{ route('admin.withdrawals.show', $withdrawal) }}">View Details</a></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="10">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
