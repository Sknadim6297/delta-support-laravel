@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border bg-white p-6">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-navy-900">{{ $invoice->invoice_number }}</h1>
        <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Download PDF</a>
    </div>
    <dl class="grid gap-2 text-sm">
        <div><dt class="font-semibold">Company</dt><dd>{{ $invoice->company_name }}</dd><dd>{{ $invoice->company_address }}</dd><dd>{{ $invoice->company_email }} {{ $invoice->company_phone }}</dd></div>
        <div><dt class="font-semibold">Member</dt><dd>{{ $invoice->member_name }} ({{ $invoice->member_id }})</dd></div>
        <div><dt class="font-semibold">Package</dt><dd>{{ $invoice->package_name }}</dd></div>
        <div><dt class="font-semibold">Historical USD amount</dt><dd>{{ $invoice->price_usd }}</dd></div>
        <div><dt class="font-semibold">Purchase date</dt><dd>{{ optional($invoice->purchased_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</dd></div>
        <div><dt class="font-semibold">Approval date</dt><dd>{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</dd></div>
        <div><dt class="font-semibold">Payment</dt><dd>{{ $invoice->currency_label }} {{ $invoice->network }}</dd><dd>Reference {{ $invoice->payment_reference }}</dd></div>
        <div><dt class="font-semibold">Status</dt><dd>{{ ucfirst($invoice->status) }}</dd></div>
    </dl>
</div>
@endsection
