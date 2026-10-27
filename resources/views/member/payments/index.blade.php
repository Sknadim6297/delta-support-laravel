@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">My Payments</h1>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Reference</th><th class="p-3">Package</th><th class="p-3">USD</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr class="border-t"><td class="p-3">{{ $purchase->reference }}</td><td class="p-3">{{ $purchase->package_name }}</td><td class="p-3">{{ $purchase->price_usd }}</td><td class="p-3">{{ $purchase->statusLabel() }}</td><td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('member.payments.show', $purchase) }}">View</a></td></tr>
            @empty
                <tr><td colspan="5" class="p-6 text-slate-500">No payment requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
