@extends('member.layouts.app')
@section('content')
<div>
    <h1>My Payments</h1>
    <div>
        <table>
            <thead><tr><th>Reference</th><th>Package</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr><td>{{ $purchase->reference }}</td><td>{{ $purchase->package_name }}</td><td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td><td>{{ $purchase->statusLabel() }}</td><td><a href="{{ route('member.payments.show', $purchase) }}">View</a></td></tr>
            @empty
                <tr><td colspan="5">No payment requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
