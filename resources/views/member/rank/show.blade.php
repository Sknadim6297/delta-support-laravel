@extends('member.layouts.app')

@section('title', 'My Rank')

@section('content')
    <main class="dashboard-content">
        <div class="form-card">
            <div class="form-title">
                <h4>My Rank</h4>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="dashboard-card h-100">
                        <div class="card-body-custom">
                            <span class="stat-label">Current Rank</span>
                            <h2 class="dashboard-title mt-2">{{ $rankData['current_rank']?->name ?? 'Unranked' }}</h2>
                            <div class="rank-detail-grid">
                                <div><span>Full Team Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['full_team_business']) }}</strong></div>
                                <div><span>Required Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['current_rank'] ? $rankData['current_rank']->required_full_team_business : 0) }}</strong></div>
                                <div><span>Levels Unlocked</span><strong>{{ $rankData['unlocked_levels'] }} / 32</strong></div>
                                <div><span>Rank Progress</span><strong>{{ number_format((float) $rankData['rank_progress'], 2) }}%</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="dashboard-card h-100">
                        <div class="card-body-custom">
                            <span class="stat-label">Next Rank</span>
                            <h2 class="dashboard-title mt-2">{{ $rankData['next_rank']?->name ?? 'Maximum Rank Achieved' }}</h2>
                            @if ($rankData['next_rank'])
                                <div class="rank-detail-grid">
                                    <div><span>Required Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['next_rank']->required_full_team_business) }}</strong></div>
                                    <div><span>Current Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['full_team_business']) }}</strong></div>
                                    <div><span>Remaining Business</span><strong>{{ \App\Support\MoneyDisplay::usdt(max(0, (float) $rankData['remaining_business'])) }}</strong></div>
                                    <div><span>Next Unlock</span><strong>{{ max(0, $rankData['next_rank']->unlocked_levels - $rankData['unlocked_levels']) }} additional levels</strong></div>
                                </div>
                            @else
                                <p class="mb-0 text-muted">You have reached the highest configured rank and all 32 levels are unlocked.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-title mt-4">
                <h4>Rank Progress Roadmap</h4>
            </div>
            <div class="rank-roadmap">
                @foreach ($roadmap as $item)
                    <div class="rank-roadmap-item roadmap-{{ $item['state'] }}">
                        <div class="rank-roadmap-marker">
                            @if ($item['state'] === 'achieved' || $item['state'] === 'current')
                                <i class="bi bi-check-lg"></i>
                            @elseif ($item['state'] === 'next')
                                <i class="bi bi-arrow-right"></i>
                            @else
                                <i class="bi bi-lock"></i>
                            @endif
                        </div>
                        <div class="rank-roadmap-content">
                            <strong>{{ $item['rank']->name }}</strong>
                            <span>{{ number_format((float) $item['rank']->required_full_team_business / 1000, 0) }}K · {{ $item['rank']->unlocked_levels }} Levels</span>
                        </div>
                        <span class="rank-roadmap-state">{{ ucfirst($item['state']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </main>
@endsection
