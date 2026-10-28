@extends('member.layouts.app')
@section('title', 'Withdrawal History')
@section('page-title', 'Withdrawal History')
@section('content')
<div>
    <p class="ds-lead">{{ $member->member_id }} · Only your stored withdrawal requests are listed.</p>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Wallet</th>
                    <th>Amount</th>
                    <th>Charge</th>
                    <th>Net</th>
                    <th>Network</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr>
                    <td>{{ $withdrawal->reference }}</td>
                    <td>{{ $withdrawal->source_wallet }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</td>
                    <td>{{ $withdrawal->network ?: 'Not configured' }}</td>
                    <td>@include('partials.withdrawal-status', ['status' => $withdrawal->status])</td>
                    <td>{{ $withdrawal->created_at?->format('d M Y') }}</td>
                    <td><a href="{{ route('member.withdrawals.show', $withdrawal) }}">View Details</a></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="9">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
