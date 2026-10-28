@extends('member.layouts.app')

@section('title', 'My Invoices')
@section('page-title', 'My Invoices')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Invoices"
        title="My Invoices"
        lead="Invoices are issued after successful package payment verification."
    />

    @if($invoices->isEmpty())
        <x-ui.card>
            <x-ui.empty title="No invoices available yet." message="Invoices are issued after successful package payment verification." />
        </x-ui.card>
    @else
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Package</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->package_name }}</td>
                            <td>{{ optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                            <td>{{ \App\Support\MoneyDisplay::format($invoice->price_usd, $invoice->denomination) }}</td>
                            <td><x-ui.badge tone="success">{{ ucfirst($invoice->status) }}</x-ui.badge></td>
                            <td>
                                <a href="{{ route('member.invoices.show', $invoice) }}">View</a>
                                <a href="{{ route('member.invoices.pdf', $invoice) }}">Download PDF</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    @endif
</div>
@endsection
