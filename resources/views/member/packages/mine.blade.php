@extends('member.layouts.app')
@section('content')
<div>
    <header>
        <h1>My Packages</h1>
        <p>Each row is a separate purchase. The amount and denomination are the values saved when the request was created.</p>
    </header>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Reference</th>
                    <th>Saved price</th>
                    <th>Status</th>
                    <th>Purchase date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr>
                    <td>
                        <div>{{ $purchase->package_name }}</div>
                        <div>{{ $purchase->package_code }}</div>
                    </td>
                    <td>{{ $purchase->reference }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</td>
                    <td>{{ $purchase->statusLabel() }}</td>
                    <td>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                    <td><a href="{{ route('member.payments.show', $purchase) }}">View details</a></td>
                </tr>
            @empty
                <tr><td colspan="6">You have not requested a package yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
