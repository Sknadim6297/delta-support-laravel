@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Withdrawal Requests</h1>
        <p class="ds-page-copy">Stored requests only. Approve, reject, complete, and transfer actions are not available.</p>
    </header>
    <form method="GET" class="ds-card mb-4 grid gap-3 md:grid-cols-3">
        <label class="ds-field"><span>Member ID</span><input name="member_id" value="{{ request('member_id') }}"></label>
        <label class="ds-field"><span>Withdrawal reference</span><input name="reference" value="{{ request('reference') }}"></label>
        <label class="ds-field"><span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\WithdrawalStatus::label($status) }}</option>
                @endforeach
            </select>
        </label>
        <label class="ds-field"><span>From date</span><input type="date" name="from_date" value="{{ request('from_date') }}"></label>
        <label class="ds-field"><span>To date</span><input type="date" name="to_date" value="{{ request('to_date') }}"></label>
        <label class="ds-field"><span>Network</span><input name="network" value="{{ request('network') }}"></label>
        <div class="ds-actions md:col-span-3"><button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Search</button></div>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900">
                <tr>
                    <th class="p-3">Reference</th>
                    <th class="p-3">Member ID</th>
                    <th class="p-3">Wallet</th>
                    <th class="p-3">Gross amount</th>
                    <th class="p-3">Charge</th>
                    <th class="p-3">Net amount</th>
                    <th class="p-3">Network</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Request date</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr class="border-t border-slate-200">
                    <td class="p-3">{{ $withdrawal->reference }}</td>
                    <td class="p-3">{{ $withdrawal->member_id }}</td>
                    <td class="p-3">{{ $withdrawal->source_wallet }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</td>
                    <td class="p-3">{{ $withdrawal->network ?: 'Not configured' }}</td>
                    <td class="p-3">@include('partials.withdrawal-status', ['status' => $withdrawal->status])</td>
                    <td class="p-3">{{ $withdrawal->created_at?->format('d M Y') }}</td>
                    <td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('admin.withdrawals.show', $withdrawal) }}">View Details</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="p-6 text-slate-500">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $withdrawals->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
