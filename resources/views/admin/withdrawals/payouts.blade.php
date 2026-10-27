@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Payout History</h1>
        <p>Completed payout records that already store a transaction ID. This page does not send a transfer.</p>
    </header>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Withdrawal reference</th>
                    <th>Member</th>
                    <th>Network</th>
                    <th>Payout TxID</th>
                    <th>Amount</th>
                    <th>Completion date</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($payouts as $payout)
                <tr>
                    <td><a href="{{ route('admin.withdrawals.show', $payout) }}">{{ $payout->reference }}</a></td>
                    <td>{{ $payout->member_id }}</td>
                    <td>{{ $payout->payout_network ?: ($payout->network ?: 'Not recorded') }}</td>
                    <td>{{ $payout->payout_txid }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($payout->net_amount ?? $payout->amount, $payout->denomination) }}</td>
                    <td>{{ $payout->updated_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No payouts recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $payouts->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
