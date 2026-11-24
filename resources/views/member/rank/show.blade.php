@extends('member.layouts.app')

@section('title', 'My Rank')
@section('page-title', 'My Rank')

@section('content')
<div class="ds-reports">
    <x-ui.page-header
        kicker="Network"
        title="My Rank"
        lead="Saved rank record only. This page does not award a rank or credit a wallet."
        :heading="true"
    />

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-medal-line" tone="teal" label="Current Rank" :value="$rankData['current_rank']?->name ?? 'Unranked'" hint="Stored rank" />
        <x-ui.stat icon="ri-funds-line" label="Full Team Business" :value="\App\Support\MoneyDisplay::usdt($rankData['full_team_business'])" hint="Team volume" />
        <x-ui.stat icon="ri-stack-line" tone="violet" label="Levels Unlocked" :value="$rankData['unlocked_levels'].' / 32'" hint="Unlock progress" />
        <x-ui.stat icon="ri-percent-line" tone="amber" label="Rank Progress" :value="number_format((float) $rankData['rank_progress'], 2).'%'" hint="Toward next rank" />
    </div>

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-arrow-up-circle-line" tone="green" label="Next Rank" :value="$rankData['next_rank']?->name ?? 'Maximum Rank Achieved'" hint="Configured next step" />
        @if ($rankData['next_rank'])
            <x-ui.stat icon="ri-target-line" label="Required Business" :value="\App\Support\MoneyDisplay::usdt($rankData['next_rank']->required_full_team_business)" hint="Next threshold" />
            <x-ui.stat icon="ri-subtract-line" tone="amber" label="Remaining Business" :value="\App\Support\MoneyDisplay::usdt(max(0, (float) $rankData['remaining_business']))" hint="Still needed" />
            <x-ui.stat icon="ri-lock-unlock-line" tone="violet" label="Next Unlock" :value="max(0, $rankData['next_rank']->unlocked_levels - $rankData['unlocked_levels']).' levels'" hint="Additional levels" />
        @else
            <x-ui.stat icon="ri-checkbox-circle-line" tone="green" label="Status" value="Complete" hint="Highest configured rank reached" />
        @endif
    </div>

    <x-ui.card title="Rank Progress Roadmap" lead="Configured ranks and unlock levels." flush>
        <div class="pv-rank-roadmap">
            @foreach ($roadmap as $item)
                <div class="pv-rank-roadmap__item is-{{ $item['state'] }}">
                    <span class="pv-rank-roadmap__icon" aria-hidden="true">
                        @if ($item['state'] === 'achieved' || $item['state'] === 'current')
                            <i class="ri-checkbox-circle-fill"></i>
                        @elseif ($item['state'] === 'next')
                            <i class="ri-flag-line"></i>
                        @else
                            <i class="ri-lock-line"></i>
                        @endif
                    </span>
                    <div class="pv-rank-roadmap__body">
                        <strong>{{ $item['rank']->name }}</strong>
                        <span>{{ number_format((float) $item['rank']->required_full_team_business / 1000, 0) }}K · {{ $item['rank']->unlocked_levels }} Levels</span>
                    </div>
                    <span class="pv-rank-roadmap__state">{{ ucfirst($item['state']) }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
@endsection
