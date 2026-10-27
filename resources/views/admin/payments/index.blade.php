@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">{{ $status === 'all' ? 'Package Purchases' : ucfirst($status) . ' Payments' }}</h1>
    <form method="GET" class="mb-4 flex gap-2">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Member, reference, or transaction" class="rounded-lg border px-3 py-2">
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Purchase</th><th class="p-3">Member</th><th class="p-3">Package</th><th class="p-3">USD</th><th class="p-3">Transaction</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr class="border-t">
                    <td class="p-3">{{ $purchase->reference }}</td>
                    <td class="p-3">{{ $purchase->member_id }}</td>
                    <td class="p-3">{{ $purchase->package_name }}</td>
                    <td class="p-3">{{ $purchase->price_usd }}</td>
                    <td class="p-3">{{ $purchase->payment_reference }}</td>
                    <td class="p-3">{{ ucfirst($purchase->status) }}</td>
                    <td class="p-3">
                        @if($purchase->status === 'pending')
                            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}" class="mb-2">@csrf<button class="rounded bg-brand-500 px-3 py-1 text-white">Approve</button></form>
                            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}" class="flex gap-2">@csrf<input name="reason" placeholder="Rejection reason" class="rounded border px-2 py-1" required><button class="rounded bg-navy-900 px-3 py-1 text-white">Reject</button></form>
                        @elseif($purchase->invoice)
                            <a class="font-semibold text-brand-700" href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice</a>
                        @else
                            {{ $purchase->rejection_reason }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-slate-500">No payments in this list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
