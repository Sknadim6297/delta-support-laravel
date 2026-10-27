@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Withdrawal Requests</h1>
        <p>Stored requests only. Approve, reject, complete, and transfer actions are not available.</p>
    </header>
    <form method="GET">
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
        <div><button type="submit">Search</button></div>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member ID</th>
                    <th>Wallet</th>
                    <th>Gross amount</th>
                    <th>Charge</th>
                    <th>Net amount</th>
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
                    <td>{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->network ?: 'Not configured' }}</td>
                    <td>@include('partials.withdrawal-status', ['status' => $withdrawal->status])</td>
                    <td>{{ $withdrawal->created_at?->format('d M Y') }}</td>
                    <td><a href="{{ route('admin.withdrawals.show', $withdrawal) }}">View Details</a></td>
                </tr>
            @empty
                <tr><td colspan="10">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
