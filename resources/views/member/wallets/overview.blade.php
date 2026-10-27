@extends('member.layouts.app')
@section('title', 'My Wallets')
@section('content')
<div>
    <header>
        <h1>My Wallets</h1>
        <p>{{ $member->member_id }} · Wallet balances are USDT. ROI and level income are not being credited.</p>
    </header>
    <div>
        <article>
            <p>ROI Wallet</p>
            <p>{{ \App\Support\MoneyDisplay::usdt($member->roi_wallet_amount) }}</p>
            <p>{{ $roiCount }} stored ROI {{ $roiCount === 1 ? 'record' : 'records' }}</p>
            <a href="{{ route('member.income.roi') }}">My ROI History</a>
        </article>
        <article>
            <p>Working Wallet</p>
            <p>{{ \App\Support\MoneyDisplay::usdt($member->working_wallet_amount) }}</p>
            <p>{{ $levelCount }} stored level income {{ $levelCount === 1 ? 'record' : 'records' }}</p>
            <a href="{{ route('member.income.level') }}">My Level Income History</a>
        </article>
        <article>
            <p>Activation Wallet</p>
            <p>{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</p>
            <p>Shown for reference. Transfers are not available here.</p>
        </article>
    </div>
</div>
@endsection
