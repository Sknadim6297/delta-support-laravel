@extends('member.layouts.app')

@section('title', 'Member Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div>
    <x-ui.page-header
        :title="'Welcome back, '.$member->member_name.'!'"
        :lead="$member->member_id.' · Member Dashboard · Sponsor ID: '.$member->sponsor_id"
    />
    @if ($member->status === 'inactive')
        <x-ui.alert type="warning">
            <p>Your account is currently inactive. Purchase a package to activate your membership.</p>
            <x-ui.button href="{{ route('member.packages.explore') }}">Browse Packages</x-ui.button>
        </x-ui.alert>
    @endif
    <p>New package purchases are saved in USDT. Historical USD purchases stay in their own total. ROI, level income, and withdrawals are not being credited.</p>

    <div>
        <article><p>Package purchases (USDT)</p><p>{{ \App\Support\MoneyDisplay::usdt($metrics['packagePurchases']['USDT']) }}</p></article>
        <article><p>Package purchases (USD)</p><p>{{ \App\Support\MoneyDisplay::format($metrics['packagePurchases']['USD'], 'USD') }}</p></article>
        <article><p>Total Investment</p><p>{{ \App\Support\MoneyDisplay::usdt($metrics['totalInvestment']) }}</p></article>
        <article><p>Active Investment</p><p>{{ \App\Support\MoneyDisplay::usdt($metrics['activeInvestment']) }}</p></article>
        <article><p>Remaining Balance</p><p>{{ \App\Support\MoneyDisplay::usdt($metrics['remainingBalance']) }}</p></article>
    </div>

    <section>
        <div>
            <h2>Rank Summary</h2>
            <a href="{{ route('member.rank') }}">View Rank</a>
        </div>
        <dl>
            <div><dt>Current Rank</dt><dd>{{ $metrics['rankSummary']['current_rank']?->name ?? 'Unranked' }}</dd></div>
            <div><dt>Full Team Business</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['rankSummary']['full_team_business']) }}</dd></div>
            <div><dt>Levels Unlocked</dt><dd>{{ $metrics['rankSummary']['unlocked_levels'] }} / 32</dd></div>
            <div><dt>Next Rank</dt><dd>{{ $metrics['rankSummary']['next_rank']?->name ?? 'Maximum Rank Achieved' }}</dd></div>
            <div><dt>Remaining Business</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['rankSummary']['remaining_business']) }}</dd></div>
        </dl>
    </section>

    <div>
        <section id="activation-wallet">
            <h2>Wallets</h2>
            <dl>
                <div id="roi-wallet"><dt>ROI Wallet</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['roiWallet']) }}</dd></div>
                <div id="working-wallet"><dt>Working Wallet</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['workingWallet']) }}</dd></div>
                <div id="salary-wallet"><dt>Salary Wallet</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['salaryWallet']) }}</dd></div>
                <div><dt>Activation Wallet</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['activationWallet']) }}</dd></div>
            </dl>
        </section>
        <section id="reports">
            <h2>Income</h2>
            <dl>
                <div><dt>Total ROI Income</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['roiIncome']) }}</dd></div>
                <div><dt>Total Level Income</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['levelIncome']) }}</dd></div>
                <div><dt>Total Salary</dt><dd>{{ \App\Support\MoneyDisplay::usdt($metrics['salaryIncome']) }}</dd></div>
            </dl>
        </section>
        <section id="genealogy">
            <h2>Network</h2>
            <dl>
                <div><dt>Active Members</dt><dd>{{ $metrics['activeMembers'] }}</dd></div>
                <div><dt>Inactive Members</dt><dd>{{ $metrics['inactiveMembers'] }}</dd></div>
                <div><dt>Rank</dt><dd>{{ $metrics['rank'] }}</dd></div>
            </dl>
        </section>
        <section id="investment">
            <h2>Team & Withdrawals</h2>
            <p><strong>Team Active Investment:</strong> {{ \App\Support\MoneyDisplay::usdt($metrics['teamActiveInvestment']) }}</p>
            <p><strong>Team Active Investment Ratio:</strong> {{ \App\Support\MoneyDisplay::usdt($metrics['teamActiveInvestment']) }} : {{ \App\Support\MoneyDisplay::usdt($metrics['teamInactiveInvestment']) }}</p>
            <p><strong>Total Withdrawal:</strong> {{ \App\Support\MoneyDisplay::usdt($metrics['totalWithdrawal']) }}</p>
        </section>
    </div>

    <section id="support">
        <h2>Referral Program</h2>
        <div>
            <input type="text" value="{{ $metrics['referralUrl'] }}" readonly>
            <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
        </div>
    </section>
</div>
@endsection
