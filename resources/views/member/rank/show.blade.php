@extends('member.layouts.app')

@section('title', 'My Rank')
@section('page-title', 'My Rank')

@section('content')
    <main>
        <div>
            <div>
                <div>
                    <div>
                        <div>
                            <span>Current Rank</span>
                            <h2>{{ $rankData['current_rank']?->name ?? 'Unranked' }}</h2>
                            <div>
                                <div><span>Full Team Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['full_team_business']) }}</strong></div>
                                <div><span>Required Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['current_rank'] ? $rankData['current_rank']->required_full_team_business : 0) }}</strong></div>
                                <div><span>Levels Unlocked</span><strong>{{ $rankData['unlocked_levels'] }} / 32</strong></div>
                                <div><span>Rank Progress</span><strong>{{ number_format((float) $rankData['rank_progress'], 2) }}%</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div>
                        <div>
                            <span>Next Rank</span>
                            <h2>{{ $rankData['next_rank']?->name ?? 'Maximum Rank Achieved' }}</h2>
                            @if ($rankData['next_rank'])
                                <div>
                                    <div><span>Required Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['next_rank']->required_full_team_business) }}</strong></div>
                                    <div><span>Current Business</span><strong>{{ \App\Support\MoneyDisplay::usdt($rankData['full_team_business']) }}</strong></div>
                                    <div><span>Remaining Business</span><strong>{{ \App\Support\MoneyDisplay::usdt(max(0, (float) $rankData['remaining_business'])) }}</strong></div>
                                    <div><span>Next Unlock</span><strong>{{ max(0, $rankData['next_rank']->unlocked_levels - $rankData['unlocked_levels']) }} additional levels</strong></div>
                                </div>
                            @else
                                <p>You have reached the highest configured rank and all 32 levels are unlocked.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h4>Rank Progress Roadmap</h4>
            </div>
            <div>
                @foreach ($roadmap as $item)
                    <div>
                        <div>
                            @if ($item['state'] === 'achieved' || $item['state'] === 'current')
                                <i></i>
                            @elseif ($item['state'] === 'next')
                                <i></i>
                            @else
                                <i></i>
                            @endif
                        </div>
                        <div>
                            <strong>{{ $item['rank']->name }}</strong>
                            <span>{{ number_format((float) $item['rank']->required_full_team_business / 1000, 0) }}K · {{ $item['rank']->unlocked_levels }} Levels</span>
                        </div>
                        <span>{{ ucfirst($item['state']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </main>
@endsection
