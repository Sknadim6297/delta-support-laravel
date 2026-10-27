@extends('member.layouts.app')

@section('title', 'Member Dashboard')

@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Welcome back, {{ $member->member_name }}!</h1>
        <p class="ds-page-copy">{{ $member->member_id }} · Member Dashboard · Sponsor ID: {{ $member->sponsor_id }}</p>
        <p class="mt-2 text-sm text-ink-500">Package purchases use the USD price saved by the administrator. ROI, level income, and withdrawals are not being credited.</p>
    </header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total Investment</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ number_format($metrics['totalInvestment'], 4) }} USDT</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Active Investment</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ number_format($metrics['activeInvestment'], 4) }} USDT</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Remaining Balance</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ number_format($metrics['remainingBalance'], 4) }} USDT</p></article>
    </div>

    <section class="ds-card mt-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-navy-900">Rank Summary</h2>
            <a href="{{ route('member.rank') }}" class="rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-semibold text-white">View Rank</a>
        </div>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-ink-500">Current Rank</dt><dd class="font-semibold text-navy-900">{{ $metrics['rankSummary']['current_rank']?->name ?? 'Unranked' }}</dd></div>
            <div><dt class="text-ink-500">Full Team Business</dt><dd class="font-semibold text-navy-900">{{ number_format((float) $metrics['rankSummary']['full_team_business'], 2) }} USDT</dd></div>
            <div><dt class="text-ink-500">Levels Unlocked</dt><dd class="font-semibold text-navy-900">{{ $metrics['rankSummary']['unlocked_levels'] }} / 32</dd></div>
            <div><dt class="text-ink-500">Next Rank</dt><dd class="font-semibold text-navy-900">{{ $metrics['rankSummary']['next_rank']?->name ?? 'Maximum Rank Achieved' }}</dd></div>
            <div><dt class="text-ink-500">Remaining Business</dt><dd class="font-semibold text-navy-900">{{ number_format(max(0, (float) $metrics['rankSummary']['remaining_business']), 2) }} USDT</dd></div>
        </dl>
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="ds-card" id="activation-wallet">
            <h2 class="mb-3 text-base font-semibold text-navy-900">Wallets</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div id="roi-wallet"><dt class="text-ink-500">ROI Wallet</dt><dd class="font-semibold">{{ number_format($metrics['roiWallet'], 4) }} USDT</dd></div>
                <div id="working-wallet"><dt class="text-ink-500">Working Wallet</dt><dd class="font-semibold">{{ number_format($metrics['workingWallet'], 4) }} USDT</dd></div>
                <div id="salary-wallet"><dt class="text-ink-500">Salary Wallet</dt><dd class="font-semibold">{{ number_format($metrics['salaryWallet'], 4) }} USDT</dd></div>
                <div><dt class="text-ink-500">Activation Wallet</dt><dd class="font-semibold">{{ number_format($metrics['activationWallet'], 4) }} USDT</dd></div>
            </dl>
        </section>
        <section class="ds-card" id="reports">
            <h2 class="mb-3 text-base font-semibold text-navy-900">Income</h2>
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-ink-500">Total ROI Income</dt><dd class="font-semibold">{{ number_format($metrics['roiIncome'], 4) }} USDT</dd></div>
                <div><dt class="text-ink-500">Total Level Income</dt><dd class="font-semibold">{{ number_format($metrics['levelIncome'], 4) }} USDT</dd></div>
                <div><dt class="text-ink-500">Total Salary</dt><dd class="font-semibold">{{ number_format($metrics['salaryIncome'], 4) }} USDT</dd></div>
            </dl>
        </section>
        <section class="ds-card" id="genealogy">
            <h2 class="mb-3 text-base font-semibold text-navy-900">Network</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-ink-500">Active Members</dt><dd class="font-semibold">{{ $metrics['activeMembers'] }}</dd></div>
                <div><dt class="text-ink-500">Inactive Members</dt><dd class="font-semibold">{{ $metrics['inactiveMembers'] }}</dd></div>
                <div><dt class="text-ink-500">Rank</dt><dd class="font-semibold">{{ $metrics['rank'] }}</dd></div>
            </dl>
        </section>
        <section class="ds-card" id="investment">
            <h2 class="mb-3 text-base font-semibold text-navy-900">Team & Withdrawals</h2>
            <p class="mb-2 text-sm"><strong>Team Active Inv. (USDT):</strong> {{ number_format($metrics['teamActiveInvestment'], 4) }}</p>
            <p class="mb-2 text-sm"><strong>Team Active Inv. Ratio (USDT):</strong> {{ number_format($metrics['teamActiveInvestment'], 4) }}:{{ number_format($metrics['teamInactiveInvestment'], 4) }}</p>
            <p class="mb-0 text-sm"><strong>Total Withdrawal (USDT):</strong> {{ number_format($metrics['totalWithdrawal'], 4) }}</p>
        </section>
    </div>

    <section class="ds-card mt-4" id="support">
        <h2 class="mb-3 text-base font-semibold text-navy-900">Referral Program</h2>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input type="text" class="ds-control" value="{{ $metrics['referralUrl'] }}" readonly>
            <button type="button" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
        </div>
    </section>
</div>
@endsection
