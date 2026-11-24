@extends('admin.layouts.app')
@section('title', 'Reward Campaigns')
@section('page-title', 'Reward Campaigns')
@push('styles')
    @vite('resources/css/features/rewards.css')
@endpush
@section('content')
<div class="pv-rewards">
    <div class="pv-rewards-toolbar">
        <div>
            <h2>Reward Campaigns</h2>
            <p>Campaigns use approved USDT team business. A draft does not qualify anyone. Gift fulfillment stays separate from wallet credits.</p>
        </div>
        <div class="pv-rewards-actions">
            <x-ui.button :href="route('admin.rewards.create')">
                <i class="ri-add-line" aria-hidden="true"></i>
                New campaign
            </x-ui.button>
            <x-ui.button :href="route('admin.rewards.eligible')" variant="secondary">
                <i class="ri-medal-line" aria-hidden="true"></i>
                Eligible members
            </x-ui.button>
            <x-ui.button :href="route('admin.rewards.reports')" variant="secondary">
                <i class="ri-file-chart-line" aria-hidden="true"></i>
                Reports
            </x-ui.button>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
    @endif

    <x-ui.table class="pv-rewards-table">
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Gift</th>
                    <th>Target</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Selected</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($campaigns as $campaign)
                <tr>
                    <td>
                        <div class="pv-campaign-name">
                            <span>{{ $campaign->title }}</span>
                            @if ($campaign->hasBanner())
                                <span class="ds-reward-media-chip" title="Banner uploaded"><i class="ri-image-line" aria-hidden="true"></i></span>
                            @endif
                            @if ($campaign->hasVideo())
                                <span class="ds-reward-media-chip" title="Video uploaded"><i class="ri-video-line" aria-hidden="true"></i></span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $campaign->gift_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($campaign->target_amount) }}</td>
                    <td>{{ $campaign->starts_on->format('d M Y') }} – {{ $campaign->ends_on->format('d M Y') }}</td>
                    <td><x-ui.badge :tone="$campaign->statusTone()">{{ $campaign->statusLabel() }}</x-ui.badge></td>
                    <td>{{ $campaign->selected_count }}</td>
                    <td><a class="pv-link" href="{{ route('admin.rewards.show', $campaign) }}">View</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="pv-empty">
                            <strong>No reward campaigns yet.</strong>
                            Create a campaign to start tracking approved USDT team business toward gifts.
                            <div style="margin-top:12px;">
                                <x-ui.button :href="route('admin.rewards.create')">Create first campaign</x-ui.button>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div>{{ $campaigns->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
