@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Payout History</h1>
        <p class="ds-page-copy">Completed payout records that already store a transaction ID. This page does not send a transfer.</p>
    </header>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900">
                <tr>
                    <th class="p-3">Withdrawal reference</th>
                    <th class="p-3">Member</th>
                    <th class="p-3">Network</th>
                    <th class="p-3">Payout TxID</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Completion date</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($payouts as $payout)
                <tr class="border-t border-slate-200">
                    <td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('admin.withdrawals.show', $payout) }}">{{ $payout->reference }}</a></td>
                    <td class="p-3">{{ $payout->member_id }}</td>
                    <td class="p-3">{{ $payout->payout_network ?: ($payout->network ?: 'Not recorded') }}</td>
                    <td class="p-3 break-all">{{ $payout->payout_txid }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($payout->net_amount ?? $payout->amount, $payout->denomination) }}</td>
                    <td class="p-3">{{ $payout->updated_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-slate-500">No payouts recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payouts->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
