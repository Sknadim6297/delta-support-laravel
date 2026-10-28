@extends('admin.layouts.app')
@section('title', $member->member_id.' Report')
@section('page-title', $member->member_name)
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Member activity and linked purchase history. Passwords and wallet credentials are not shown.">
        <x-slot:actions>
            <a class="ds-btn" href="{{ route('admin.catalog-reports.show', ['report' => 'members']) }}">Back to member reports</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-table-wrap">
        <table>
            <tbody>
                <tr><th>Member ID</th><td>{{ $member->member_id }}</td></tr>
                <tr><th>Full name</th><td>{{ $member->member_name }}</td></tr>
                <tr><th>Email</th><td>{{ $member->email }}</td></tr>
                <tr><th>Mobile</th><td>{{ $member->mobile_no }}</td></tr>
                <tr><th>Sponsor</th><td>{{ $member->sponsor_id }} · {{ $member->sponsor_name }}</td></tr>
                <tr><th>Registered</th><td>{{ $member->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td></tr>
                <tr><th>Account status</th><td>{{ ucfirst($member->status) }}</td></tr>
                <tr><th>Last login</th><td>Not tracked</td></tr>
                <tr><th>Activation date</th><td>Not tracked</td></tr>
                <tr><th>Activated by</th><td>Not tracked</td></tr>
            </tbody>
        </table>
    </div>

    <h2>Purchase and payment history</h2>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Network</th>
                    <th>Transaction ID</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th>Reviewed by</th>
                    <th>Rejection reason</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->reference }}</td>
                    <td>{{ $purchase->package_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::amount($purchase->price_usd) }} {{ $purchase->denomination ?: 'Not stored' }}</td>
                    <td>{{ $purchase->network ?: 'Not stored' }}</td>
                    <td>{{ $purchase->payment_reference }}</td>
                    <td>{{ $purchase->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td><x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge></td>
                    <td>{{ $purchase->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?: 'Not stored' }}</td>
                    <td>{{ $purchase->reviewer->name ?? 'Not stored' }}</td>
                    <td>{{ $purchase->rejection_reason }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No purchase records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $purchases->links('pagination::bootstrap-5') }}</div>

    <h2>Recorded activity</h2>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr><th>Action</th><th>Started</th><th>Ended</th><th>Admin</th></tr>
            </thead>
            <tbody>
            @forelse ($activity as $entry)
                <tr>
                    <td>{{ ucfirst($entry->action) }}</td>
                    <td>{{ $entry->started_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td>{{ $entry->ended_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?: 'Not stored' }}</td>
                    <td>{{ $entry->admin->name ?? 'Not stored' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No activity records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
