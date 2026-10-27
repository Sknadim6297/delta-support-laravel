@extends('member.layouts.app')
@section('title', $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet')
@section('content')
<div>
    <header>
        <h1>{{ $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet' }}</h1>
        <p>Your stored USDT balance. This page cannot credit or withdraw funds.</p>
    </header>
    <article>
        <p>Available balance</p>
        <p>{{ \App\Support\MoneyDisplay::usdt($balance) }}</p>
        <p>{{ $count }} stored {{ $wallet === 'roi' ? 'ROI' : 'level income' }} {{ $count === 1 ? 'record' : 'records' }}.</p>
        <a href="{{ $wallet === 'roi' ? route('member.income.roi') : route('member.income.level') }}">View history</a>
    </article>
</div>
@endsection
