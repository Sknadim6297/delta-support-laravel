@props(['invoice'])
<article class="mx-auto max-w-3xl bg-white text-navy-900">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b-4 border-brand-500 bg-navy-900 px-6 py-5 text-white">
        <div class="flex items-center gap-3">
            <img src="{{ asset('site/img/logo.png') }}" alt="Delta Support" class="h-12 w-auto rounded bg-white p-1">
            <div>
                <p class="text-sm font-semibold tracking-wide">{{ $invoice->company_name ?: 'Company name not set' }}</p>
                <p class="text-xs text-white/80">{{ $invoice->denomination ?: 'USD' }} invoice</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-widest text-white/70">Invoice</p>
            <p class="text-lg font-bold">{{ $invoice->invoice_number }}</p>
        </div>
    </header>
    <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Bill to</p>
            <p class="mt-1 font-semibold">{{ $invoice->member_name }}</p>
            <p class="text-sm">{{ $invoice->member_id }}</p>
        </div>
        <div class="sm:text-right">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Status</p>
            <p class="mt-1 inline-flex rounded-full bg-brand-500 px-3 py-1 text-xs font-semibold text-white">{{ ucfirst($invoice->status) }}</p>
            <p class="mt-2 text-sm">Approved {{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
            <p class="text-sm">Purchased {{ optional($invoice->purchased_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
        </div>
    </div>
    <div class="px-6">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-y border-slate-200 text-left">
                    <th class="py-2 font-semibold">Description</th>
                    <th class="py-2 text-right font-semibold">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-100">
                    <td class="py-3">
                        <p class="font-semibold">{{ $invoice->package_name }}</p>
                        @if($invoice->package_code)<p class="text-slate-600">{{ $invoice->package_code }}</p>@endif
                        @if($invoice->package_details)<p class="mt-2 whitespace-pre-line text-slate-600">{{ $invoice->package_details }}</p>@endif
                    </td>
                    <td class="py-3 text-right font-semibold">{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                </tr>
            </tbody>
        </table>
        <div class="mt-4 flex justify-end">
            <p class="rounded-lg bg-slate-50 px-4 py-3 text-base font-bold">Total {{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</p>
        </div>
    </div>
    <footer class="mt-6 grid gap-4 px-6 pb-6 text-sm sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Payment</p>
            <p class="mt-1">{{ trim(($invoice->currency_label ?: '').' '.($invoice->network ?: '')) ?: 'Recorded at approval' }}</p>
            <p class="break-all">Transaction {{ $invoice->payment_reference }}</p>
            @if($invoice->purchase_reference)<p>Purchase {{ $invoice->purchase_reference }}</p>@endif
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Issued by</p>
            <p class="mt-1">{{ $invoice->company_name ?: 'Company name not set' }}</p>
            @if($invoice->company_address)<p class="whitespace-pre-line">{{ $invoice->company_address }}</p>@endif
            @if($invoice->company_email || $invoice->company_phone)<p>{{ trim($invoice->company_email.' '.$invoice->company_phone) }}</p>@endif
        </div>
    </footer>
</article>
