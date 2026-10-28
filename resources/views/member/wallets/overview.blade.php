@extends('member.layouts.app')
@section('title', 'My Wallets')
@section('page-title', 'My Wallets')
@section('content')
<div>
    <p class="ds-lead">{{ $member->member_id }} · @if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid'))Local test data. Production payout generation remains disabled.@else Wallet balances are USDT. ROI and level income are not being credited.@endif</p>
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
