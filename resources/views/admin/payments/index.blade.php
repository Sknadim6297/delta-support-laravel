@extends('admin.layouts.app')

@section('title', $status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments')
@section('page-title', $status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        :title="$status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments'"
        lead="Approve a payment only after you confirm the amount arrived on the configured network. A reference or QR code is not proof."
    />
    <form class="ds-filter-form" method="GET">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Member, reference, or transaction" aria-label="Search payments">
        <button>Search</button>
    </form>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Member</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Network</th>
                    <th>Transaction Reference</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->reference }}</td>
                    <td>{{ $purchase->member_id }}@if($purchase->member) · {{ $purchase->member->member_name }}@endif</td>
                    <td>{{ $purchase->package_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                    <td>{{ $purchase->network ?: 'Not recorded' }}</td>
                    <td>{{ $purchase->payment_reference }}</td>
                    <td>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                    <td><x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge></td>
                    <td>
                        <a href="{{ route('admin.payments.show', $purchase) }}">Review</a>
                        @if($purchase->status === 'pending')
                            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}">@csrf<button>Approve</button></form>
                            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}">@csrf<input name="reason" placeholder="Rejection reason" required maxlength="1000"><button>Reject</button></form>
                        @elseif($purchase->invoice)
                            <a href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice</a>
                        @else
                            {{ $purchase->rejection_reason }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">No payments in this list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
