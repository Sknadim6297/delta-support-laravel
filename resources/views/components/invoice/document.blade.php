@props(['invoice'])

<article class="ds-invoice">
    <header class="ds-invoice-top">
        <div class="ds-invoice-brand">
            <img src="{{ asset('site/img/logo.png') }}" alt="Delta Support">
            <div>
                <p class="ds-invoice-company">{{ $invoice->company_name ?: 'Company name not set' }}</p>
                <p>{{ $invoice->denomination ?: 'USD' }} invoice</p>
            </div>
        </div>
        <div class="ds-invoice-number">
            <p>Invoice</p>
            <strong>{{ $invoice->invoice_number }}</strong>
        </div>
    </header>

    <div class="ds-invoice-parties">
        <div>
            <p>Bill to</p>
            <strong>{{ $invoice->member_name }}</strong>
            <p>{{ $invoice->member_id }}</p>
        </div>
        <div>
            <p>Status</p>
            <strong>{{ ucfirst($invoice->status) }}</strong>
            <p>Approved {{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
            <p>Purchased {{ optional($invoice->purchased_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
        </div>
    </div>

    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $invoice->package_name }}</strong>
                        @if($invoice->package_code)<p>{{ $invoice->package_code }}</p>@endif
                        @if($invoice->package_details)<p>{{ $invoice->package_details }}</p>@endif
                    </td>
                    <td>{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="ds-invoice-total">Total {{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</p>

    <footer class="ds-invoice-foot">
        <div>
            <p>Payment</p>
            <strong>{{ trim(($invoice->currency_label ?: '').' '.($invoice->network ?: '')) ?: 'Recorded at approval' }}</strong>
            <p>Transaction {{ $invoice->payment_reference }}</p>
            @if($invoice->purchase_reference)<p>Purchase {{ $invoice->purchase_reference }}</p>@endif
        </div>
        <div>
            <p>Issued by</p>
            <strong>{{ $invoice->company_name ?: 'Company name not set' }}</strong>
            @if($invoice->company_address)<p>{{ $invoice->company_address }}</p>@endif
            @if($invoice->company_email || $invoice->company_phone)<p>{{ trim($invoice->company_email.' '.$invoice->company_phone) }}</p>@endif
        </div>
    </footer>
</article>
