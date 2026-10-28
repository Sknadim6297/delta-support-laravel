@extends('member.layouts.app')
@section('title', 'My Rewards')
@section('page-title', 'My Rewards')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Team business is approved USDT package volume for you and your downline. Reaching a target on this page does not issue a gift." />
    @forelse ($rows as $row)
        @php
            $campaign = $row['campaign'];
            $qualification = $row['qualification'];
            $assignment = $qualification?->assignment;
            $delivery = $assignment?->delivery;
            $selectedHere = $assignment && $assignment->status === \App\Models\RewardAssignment::SELECTED;
        @endphp
        <x-ui.card :title="$campaign->title" :subtitle="$campaign->starts_on->format('d M Y').' – '.$campaign->ends_on->format('d M Y')">
            @if ($campaign->gift_image_path)
                <img src="{{ route('member.rewards.image', $campaign) }}" alt="" class="ds-package-thumb">
            @endif
            <p>{{ $campaign->gift_name }}</p>
            @if ($campaign->gift_description)
                <p>{{ $campaign->gift_description }}</p>
            @endif
            <div class="ds-stat-grid">
                <x-ui.stat label="Required" :value="\App\Support\MoneyDisplay::usdt($row['target'])" />
                <x-ui.stat label="Achieved" :value="\App\Support\MoneyDisplay::usdt($row['achieved'])" />
                <x-ui.stat label="Remaining" :value="\App\Support\MoneyDisplay::usdt($row['remaining'])" />
                <x-ui.stat label="Progress" :value="$row['percent'].'%'" />
            </div>
            <p>
                <x-ui.badge :tone="$campaign->statusTone()">{{ $campaign->statusLabel() }}</x-ui.badge>
                @if ($qualification)
                    <x-ui.badge :tone="$qualification->statusTone()">{{ $qualification->statusLabel() }}</x-ui.badge>
                @else
                    <x-ui.badge tone="muted">Not calculated yet</x-ui.badge>
                @endif
            </p>
            @if ($selectedHere)
                <p>Selected reward: {{ $assignment->gift_name }}</p>
                @if ($qualification->qualified_at)
                    <p>Qualified {{ $qualification->qualified_at->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
                @endif
                @if ($delivery)
                    <p>Delivery: {{ $delivery->statusLabel() }}</p>
                    @if ($delivery->tracking_reference)
                        <p>Tracking: {{ $delivery->tracking_reference }}</p>
                    @endif
                    @if ($delivery->method)
                        <p>Method: {{ $delivery->method }}</p>
                    @endif
                @endif
            @elseif ($qualification && $qualification->status === \App\Models\RewardQualification::QUALIFIED)
                <p>You reached this target. A later campaign may be the reward that is selected.</p>
            @else
                <p>This reward is not a confirmed gift.</p>
            @endif
            @if ($assignment?->conflict_campaign_id)
                <p>A later campaign also qualifies. An admin will review it. This gift stays as recorded.</p>
            @endif
        </x-ui.card>
    @empty
        <x-ui.card title="Campaigns"><p>No reward campaigns are open.</p></x-ui.card>
    @endforelse

    <x-ui.card title="Reward history">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Gift</th>
                        <th>Selection</th>
                        <th>Delivery</th>
                        <th>Tracking</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($history as $assignment)
                    <tr>
                        <td>{{ $assignment->campaign?->title }}</td>
                        <td>{{ $assignment->gift_name }}</td>
                        <td>{{ $assignment->statusLabel() }}</td>
                        <td>{{ $assignment->delivery?->statusLabel() ?? '—' }}</td>
                        <td>{{ $assignment->delivery?->tracking_reference }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No reward has been selected yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
