@extends('admin.layouts.app')
@section('title', 'Reward Reports')
@section('page-title', 'Reward Reports')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Counts are stored reward records. Business amounts are not estimated.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.eligible')" variant="secondary">Eligible members</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="ds-stat-grid">
        <x-ui.stat label="Pending fulfillment" :value="(int) ($deliveries['pending'] ?? 0) + (int) ($deliveries['approved'] ?? 0)" />
        <x-ui.stat label="Processing" :value="$deliveries['processing'] ?? 0" />
        <x-ui.stat label="Dispatched" :value="$deliveries['shipped'] ?? 0" />
        <x-ui.stat label="Delivered" :value="$deliveries['delivered'] ?? 0" />
    </div>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th>Qualified</th>
                    <th>Selected</th>
                    <th>Superseded assignments</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($campaigns as $campaign)
                <tr>
                    <td><a href="{{ route('admin.rewards.show', $campaign) }}">{{ $campaign->title }}</a></td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($campaign->target_amount) }}</td>
                    <td>{{ $campaign->statusLabel() }}</td>
                    <td>{{ $campaign->qualified_count }}</td>
                    <td>{{ $campaign->selected_count }}</td>
                    <td>{{ $campaign->superseded_count }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No campaigns have been saved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <p><a href="{{ route('admin.rewards.export', ['format' => 'csv', 'status' => 'superseded']) }}">Export superseded rewards</a></p>
    <p><a href="{{ route('admin.rewards.export', ['format' => 'csv', 'status' => 'selected']) }}">Export selected rewards</a></p>
</div>
@endsection
