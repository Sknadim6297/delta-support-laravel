@extends('admin.layouts.app')
@section('content')
@php
    $title = match ($wallet) {
        'roi' => 'ROI Wallet',
        'working' => 'Working Wallet',
        default => 'Activation Wallet',
    };
@endphp
<div>
    <header>
        <h1>{{ $title }}</h1>
        <p>Read-only USDT balances. Crediting, ROI accrual, and payouts are not enabled.</p>
    </header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search member ID or name">
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td>{{ ucfirst((string) $member->status) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($member->{$column}) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
