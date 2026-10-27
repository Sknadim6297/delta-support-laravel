@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>All Invoices</h1>
        <p>Each approved purchase has one invoice. The amount and denomination are the values saved at approval.</p>
    </header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Invoice, member, or package" aria-label="Search invoices">
        <input type="date" name="from" value="{{ request('from') }}" aria-label="From date">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="To date">
        <select name="status" aria-label="Invoice status">
            <option value="">All statuses</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
        </select>
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Invoice number</th>
                    <th>Member ID</th>
                    <th>Package</th>
                    <th>Invoice date</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->member_id }}</td>
                    <td>{{ $invoice->package_name }}</td>
                    <td>{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                    <td>{{ trim(($invoice->currency_label ?: '').' '.($invoice->payment_reference ?: '')) }}</td>
                    <td><span>{{ ucfirst($invoice->status) }}</span></td>
                    <td>
                        <a href="{{ route('admin.invoices.show', $invoice) }}">View</a>
                        <a href="{{ route('admin.invoices.print', $invoice) }}">Print</a>
                        <a href="{{ route('admin.invoices.pdf', $invoice) }}">PDF</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No invoices yet. An invoice is created only after a payment is approved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
