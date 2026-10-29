@extends('member.layouts.app')

@section('title', 'My Payments')
@section('page-title', 'My Payments')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Payments"
        title="My Payments"
        lead="A submitted reference is a claim. Pending Verification stays in place until an administrator reviews the payment."
    />

    @if($purchases->isEmpty())
        <x-ui.card>
            <x-ui.empty title="No payment requests yet." message="Submit a transaction reference from a package page. The request stays pending until it is reviewed." />
        </x-ui.card>
    @else
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Transaction Reference</th>
                        <th>Submitted</th>
                        <th>Reviewed</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $purchase)
                        <tr>
                            <td>{{ $purchase->reference }}</td>
                            <td>{{ $purchase->package_name }}</td>
                            <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                            <td>{{ $purchase->payment_reference }}</td>
                            <td>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                            <td>{{ $purchase->reviewed_at ? $purchase->reviewed_at->timezone('Asia/Kolkata')->format('d M Y H:i') : 'Not recorded' }}</td>
                            <td>
                                <x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge>
                                @if($purchase->rejection_reason)
                                    <p>{{ $purchase->rejection_reason }}</p>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('member.payments.show', $purchase) }}">View Details</a>
                                @if($purchase->invoice)
                                    <a href="{{ route('member.invoices.show', $purchase->invoice) }}">Invoice</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $purchases->links('pagination::bootstrap-5') }}
    @endif
</div>
@endsection
