@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">My Invoices</h1>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Invoice</th><th class="p-3">Package</th><th class="p-3">USD</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr class="border-t"><td class="p-3">{{ $invoice->invoice_number }}</td><td class="p-3">{{ $invoice->package_name }}</td><td class="p-3">{{ $invoice->price_usd }}</td><td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('member.invoices.show', $invoice) }}">View</a></td></tr>
            @empty
                <tr><td colspan="4" class="p-6 text-slate-500">Invoices appear after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
