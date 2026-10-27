@extends('admin.layouts.app')
@section('content')
<div>
    <h1>{{ $status === 'all' ? 'Package Purchases' : ucfirst($status) . ' Payments' }}</h1>
    <form method="GET">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Member, reference, or transaction">
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead><tr><th>Purchase</th><th>Member</th><th>Package</th><th>Amount</th><th>Transaction</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->reference }}</td>
                    <td>{{ $purchase->member_id }}</td>
                    <td>{{ $purchase->package_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                    <td>{{ $purchase->payment_reference }}</td>
                    <td>{{ $purchase->statusLabel() }}</td>
                    <td>
                        <a href="{{ route('admin.payments.show', $purchase) }}">Review</a>
                        @if($purchase->status === 'pending')
                            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}">@csrf<button>Approve</button></form>
                            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}">@csrf<input name="reason" placeholder="Rejection reason" required><button>Reject</button></form>
                        @elseif($purchase->invoice)
                            <a href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice</a>
                        @else
                            {{ $purchase->rejection_reason }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No payments in this list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
