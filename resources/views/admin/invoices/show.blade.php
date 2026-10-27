@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="ds-page-title">Invoice {{ $invoice->invoice_number }}</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.invoices.print', $invoice) }}" class="rounded-lg border border-navy-900 px-4 py-2 text-sm font-semibold text-navy-900">Print</a>
            <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Download PDF</a>
        </div>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
        <x-invoice.document :invoice="$invoice" />
    </div>
</div>
@endsection
