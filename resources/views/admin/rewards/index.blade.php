@extends('admin.layouts.app')
@section('title', 'Reward Campaigns')
@section('page-title', 'Reward Campaigns')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Campaigns use approved USDT team business. A draft does not qualify anyone.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.create')">New campaign</x-ui.button>
            <x-ui.button :href="route('admin.rewards.eligible')" variant="secondary">Eligible members</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('success'))
        <x-ui.alert>{{ session('success') }}</x-ui.alert>
    @endif
    <x-ui.table>
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
                    <td>{{ $campaign->title }}</td>
                    <td>{{ $campaign->gift_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($campaign->target_amount) }}</td>
                    <td>{{ $campaign->starts_on->format('d M Y') }} – {{ $campaign->ends_on->format('d M Y') }}</td>
                    <td><x-ui.badge :tone="$campaign->statusTone()">{{ $campaign->statusLabel() }}</x-ui.badge></td>
                    <td>{{ $campaign->selected_count }}</td>
                    <td><a href="{{ route('admin.rewards.show', $campaign) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No reward campaigns yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div>{{ $campaigns->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
