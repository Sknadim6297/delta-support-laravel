@extends('member.layouts.app')
@section('title', 'Withdrawal History')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Withdrawal History</h1>
        <p class="ds-page-copy">{{ $member->member_id }} · Only your stored withdrawal requests are listed.</p>
    </header>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900">
                <tr>
                    <th class="p-3">Reference</th>
                    <th class="p-3">Wallet</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Charge</th>
                    <th class="p-3">Net</th>
                    <th class="p-3">Network</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Created</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr class="border-t border-slate-200">
                    <td class="p-3">{{ $withdrawal->reference }}</td>
                    <td class="p-3">{{ $withdrawal->source_wallet }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->network ?: 'Not configured' }}</td>
                    <td class="p-3">@include('partials.withdrawal-status', ['status' => $withdrawal->status])</td>
                    <td class="p-3">{{ $withdrawal->created_at?->format('d M Y') }}</td>
                    <td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('member.withdrawals.show', $withdrawal) }}">View Details</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-6 text-slate-500">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
