@extends('member.layouts.app')

@section('title', 'Member Dashboard')

@section('content')
    <div class="row g-4">
        <div class="col-12" id="profile">
            <div class="dashboard-card">
                <div class="card-body-custom">
                    <h2 class="dashboard-title">Welcome back, {{ $member->member_name }}!</h2>
                    <div class="dashboard-subtitle">{{ $member->member_id }} · Member Dashboard</div>
                    <p class="mb-0 mt-3">Sponsor ID: {{ $member->sponsor_id }}</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="row g-4">
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="summary-card card-pink h-100">
                        <div class="summary-icon"><i class="bi bi-bar-chart-fill"></i></div>
                        <div class="summary-number">{{ number_format($metrics['totalInvestment'], 4) }} USDT</div>
                        <div class="summary-name">Total Investment</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="summary-card card-purple h-100">
                        <div class="summary-icon"><i class="bi bi-activity"></i></div>
                        <div class="summary-number">{{ number_format($metrics['activeInvestment'], 4) }} USDT</div>
                        <div class="summary-name">Active Investment</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="summary-card card-blue h-100">
                        <div class="summary-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="summary-number">{{ number_format($metrics['remainingBalance'], 4) }} USDT</div>
                        <div class="summary-name">Remaining Balance</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="dashboard-card rank-summary-card">
                <div class="card-body-custom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="dashboard-title mb-0">Rank Summary</h4>
                        <a href="{{ route('member.rank') }}" class="btn btn-primary btn-sm"><i class="bi bi-award me-1"></i>View Rank</a>
                    </div>
                    <div class="rank-summary-grid mt-3">
                        <div><span class="stat-label">Current Rank</span><strong>{{ $metrics['rankSummary']['current_rank']?->name ?? 'Unranked' }}</strong></div>
                        <div><span class="stat-label">Full Team Business</span><strong>{{ number_format((float) $metrics['rankSummary']['full_team_business'], 2) }} USDT</strong></div>
                        <div><span class="stat-label">Levels Unlocked</span><strong>{{ $metrics['rankSummary']['unlocked_levels'] }} / 32</strong></div>
                        <div><span class="stat-label">Next Rank</span><strong>{{ $metrics['rankSummary']['next_rank']?->name ?? 'Maximum Rank Achieved' }}</strong></div>
                        <div><span class="stat-label">Remaining Business</span><strong>{{ number_format(max(0, (float) $metrics['rankSummary']['remaining_business']), 2) }} USDT</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6" id="activation-wallet">
            <div class="dashboard-card h-100">
                <div class="card-body-custom">
                    <h4 class="dashboard-title">Wallets</h4>
                    <div class="stat-row">
                        <div class="stat-box" id="roi-wallet"><div class="stat-icon icon-pink"><i class="bi bi-pie-chart"></i></div><div class="stat-content"><span class="stat-label">ROI Wallet</span><span class="stat-value">{{ number_format($metrics['roiWallet'], 4) }} USDT</span></div></div>
                        <div class="stat-box" id="working-wallet"><div class="stat-icon icon-blue"><i class="bi bi-wallet"></i></div><div class="stat-content"><span class="stat-label">Working Wallet</span><span class="stat-value">{{ number_format($metrics['workingWallet'], 4) }} USDT</span></div></div>
                        <div class="stat-box" id="salary-wallet"><div class="stat-icon icon-yellow"><i class="bi bi-cash-stack"></i></div><div class="stat-content"><span class="stat-label">Salary Wallet</span><span class="stat-value">{{ number_format($metrics['salaryWallet'], 4) }} USDT</span></div></div>
                        <div class="stat-box"><div class="stat-icon icon-purple"><i class="bi bi-wallet2"></i></div><div class="stat-content"><span class="stat-label">Activation Wallet</span><span class="stat-value">{{ number_format($metrics['activationWallet'], 4) }} USDT</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6" id="reports">
            <div class="dashboard-card h-100">
                <div class="card-body-custom">
                    <h4 class="dashboard-title">Income</h4>
                    <div class="stat-row">
                        <div class="stat-box"><div class="stat-icon icon-pink"><i class="bi bi-graph-up"></i></div><div class="stat-content"><span class="stat-label">Total ROI Income</span><span class="stat-value">{{ number_format($metrics['roiIncome'], 4) }} USDT</span></div></div>
                        <div class="stat-box"><div class="stat-icon icon-purple"><i class="bi bi-diagram-3"></i></div><div class="stat-content"><span class="stat-label">Total Level Income</span><span class="stat-value">{{ number_format($metrics['levelIncome'], 4) }} USDT</span></div></div>
                        <div class="stat-box"><div class="stat-icon icon-yellow"><i class="bi bi-cash"></i></div><div class="stat-content"><span class="stat-label">Total Salary</span><span class="stat-value">{{ number_format($metrics['salaryIncome'], 4) }} USDT</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6" id="genealogy">
            <div class="dashboard-card h-100">
                <div class="card-body-custom">
                    <h4 class="dashboard-title">Network</h4>
                    <div class="stat-row">
                        <div class="stat-box"><div class="stat-icon icon-blue"><i class="bi bi-people-fill"></i></div><div class="stat-content"><span class="stat-label">Active Members</span><span class="stat-value">{{ $metrics['activeMembers'] }}</span></div></div>
                        <div class="stat-box"><div class="stat-icon icon-orange"><i class="bi bi-people"></i></div><div class="stat-content"><span class="stat-label">Inactive Members</span><span class="stat-value">{{ $metrics['inactiveMembers'] }}</span></div></div>
                        <div class="stat-box"><div class="stat-icon icon-purple"><i class="bi bi-award"></i></div><div class="stat-content"><span class="stat-label">Rank</span><span class="stat-value">{{ $metrics['rank'] }}</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6" id="investment">
            <div class="dashboard-card h-100">
                <div class="card-body-custom">
                    <h4 class="dashboard-title">Team & Withdrawals</h4>
                    <p class="mb-2"><strong>Team Active Inv. (USDT):</strong> {{ number_format($metrics['teamActiveInvestment'], 4) }}</p>
                    <p class="mb-2"><strong>Team Active Inv. Ratio (USDT):</strong> {{ number_format($metrics['teamActiveInvestment'], 4) }}:{{ number_format($metrics['teamInactiveInvestment'], 4) }}</p>
                    <p class="mb-0"><strong>Total Withdrawal (USDT):</strong> {{ number_format($metrics['totalWithdrawal'], 4) }}</p>
                </div>
            </div>
        </div>

        <div class="col-12" id="support">
            <div class="dashboard-card">
                <div class="card-body-custom">
                    <h4 class="dashboard-title">Referral Program</h4>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ $metrics['referralUrl'] }}" readonly>
                        <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)"><i class="bi bi-copy me-1"></i>Copy</button>
                    </div>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <button type="button" class="btn btn-outline-primary"><i class="bi bi-share me-1"></i>Share</button>
                        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-pin-angle me-1"></i>Post Pin</button>
                        <button type="button" class="btn btn-outline-danger"><i class="bi bi-envelope me-1"></i>Email</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-center small text-muted">© {{ date('Y') }} Delta Support. All rights reserved.</div>
    </div>
@endsection
