@extends('admin.layouts.app')

@section('title', $status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments')
@section('page-title', $status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments')

@section('content')
<div class="ds-table-panel">
    <div class="ds-table-toolbar">
        <div>
            <h2>{{ $status === 'all' ? 'Package Purchases' : ucfirst($status).' Payments' }}</h2>
            <p>Approve a payment only after you confirm the amount arrived on the configured network. A reference or QR code is not proof.</p>
        </div>
        <form class="ds-table-search" method="GET">
            @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <i class="ri-search-line" aria-hidden="true"></i>
            <input name="q" value="{{ request('q') }}" placeholder="Search by reference, member, package..." aria-label="Search payments">
            <button type="submit">Search</button>
        </form>
    </div>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Payment Reference</th>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Currency</th>
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
                    <td>{{ $purchase->member_id }}</td>
                    <td>{{ $purchase->member->member_name ?? 'Not stored' }}</td>
                    <td>{{ $purchase->package_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::amount($purchase->price_usd) }}</td>
                    <td>{{ $purchase->denomination ?: 'Not stored' }}</td>
                    <td>{{ $purchase->network ?: 'Not recorded' }}</td>
                    <td>{{ $purchase->payment_reference }}</td>
                    <td>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                    <td><x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge></td>
                    <td class="ds-row-actions">
                        <a href="{{ route('admin.payments.show', $purchase) }}">Review</a>
                        @if($purchase->status === 'pending')
                            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}">@csrf<button>Approve</button></form>
                            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}">@csrf<input name="reason" placeholder="Rejection reason" required maxlength="1000" aria-label="Rejection reason"><button>Reject</button></form>
                        @elseif($purchase->invoice)
                            <a href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice</a>
                        @else
                            {{ $purchase->rejection_reason }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11">No payments in this list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $purchases->links('pagination::bootstrap-5') }}
</div>
@endsection
