@extends('member.layouts.app')
@section('content')
<div>
    <header>
        <h1>My Invoices</h1>
        <p>These are the invoices issued for your approved package purchases.</p>
    </header>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Invoice number</th>
                    <th>Package</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->package_name }}</td>
                    <td>{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                    <td>
                        <a href="{{ route('member.invoices.show', $invoice) }}">View</a>
                        <a href="{{ route('member.invoices.print', $invoice) }}">Print</a>
                        <a href="{{ route('member.invoices.pdf', $invoice) }}">PDF</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Invoices appear after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
