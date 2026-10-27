@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Wallet Overview</h1>
        <p>Wallet balances are USDT. Historical USD package purchases are not included in these totals. Crediting and payouts are not enabled.</p>
    </header>
    <div>
        @foreach($notices as $notice)
            <p role="status">{{ $notice }}</p>
        @endforeach
    </div>
    <div>
        <article><p>ROI Wallet</p><p>{{ \App\Support\MoneyDisplay::usdt($totals['roi']) }}</p></article>
        <article><p>Working Wallet</p><p>{{ \App\Support\MoneyDisplay::usdt($totals['working']) }}</p></article>
        <article><p>Activation Wallet</p><p>{{ \App\Support\MoneyDisplay::usdt($totals['activation']) }}</p></article>
        <article><p>Stored records</p><p>{{ $roiCount }} ROI · {{ $levelCount }} level income</p></article>
    </div>
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
                    <th>ROI Wallet</th>
                    <th>Working Wallet</th>
                    <th>Activation Wallet</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($member->roi_wallet_amount) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($member->working_wallet_amount) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
