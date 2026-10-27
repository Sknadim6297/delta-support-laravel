@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">My Invoices</h1>
        <p class="ds-page-copy">These are the invoices issued for your approved package purchases.</p>
    </header>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Invoice number</th>
                    <th class="p-3">Package</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $invoice->invoice_number }}</td>
                    <td class="p-3">{{ $invoice->package_name }}</td>
                    <td class="p-3">{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                    <td class="p-3">
                        <a class="font-semibold text-brand-700" href="{{ route('member.invoices.show', $invoice) }}">View</a>
                        <a class="ml-3 font-semibold text-navy-900" href="{{ route('member.invoices.print', $invoice) }}">Print</a>
                        <a class="ml-3 font-semibold text-navy-900" href="{{ route('member.invoices.pdf', $invoice) }}">PDF</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-slate-500">Invoices appear after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
