@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">All Invoices</h1>
    <form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="rounded-lg border px-3 py-2" placeholder="Invoice, member, or package"><button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button></form>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Invoice</th><th class="p-3">Member</th><th class="p-3">Package</th><th class="p-3">USD</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr class="border-t"><td class="p-3">{{ $invoice->invoice_number }}</td><td class="p-3">{{ $invoice->member_id }}</td><td class="p-3">{{ $invoice->package_name }}</td><td class="p-3">{{ $invoice->price_usd }}</td><td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('admin.invoices.show', $invoice) }}">View</a></td></tr>
            @empty
                <tr><td colspan="5" class="p-6 text-slate-500">No invoices yet. An invoice is created only after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
