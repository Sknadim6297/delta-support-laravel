@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">All Invoices</h1>
        <p class="ds-page-copy">Each approved purchase has one invoice. The amount and denomination are the values saved at approval.</p>
    </header>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="rounded-lg border border-slate-200 px-3 py-2" placeholder="Invoice, member, or package" aria-label="Search invoices">
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-200 px-3 py-2" aria-label="From date">
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-200 px-3 py-2" aria-label="To date">
        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2" aria-label="Invoice status">
            <option value="">All statuses</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
        </select>
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Invoice number</th>
                    <th class="p-3">Member ID</th>
                    <th class="p-3">Package</th>
                    <th class="p-3">Invoice date</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $invoice->invoice_number }}</td>
                    <td class="p-3">{{ $invoice->member_id }}</td>
                    <td class="p-3">{{ $invoice->package_name }}</td>
                    <td class="p-3">{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                    <td class="p-3">{{ trim(($invoice->currency_label ?: '').' '.($invoice->payment_reference ?: '')) }}</td>
                    <td class="p-3"><span class="rounded-full bg-brand-500 px-2 py-1 text-xs font-semibold text-white">{{ ucfirst($invoice->status) }}</span></td>
                    <td class="p-3">
                        <a class="font-semibold text-brand-700" href="{{ route('admin.invoices.show', $invoice) }}">View</a>
                        <a class="ml-3 font-semibold text-navy-900" href="{{ route('admin.invoices.print', $invoice) }}">Print</a>
                        <a class="ml-3 font-semibold text-navy-900" href="{{ route('admin.invoices.pdf', $invoice) }}">PDF</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-slate-500">No invoices yet. An invoice is created only after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
