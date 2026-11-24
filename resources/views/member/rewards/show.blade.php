@extends('member.layouts.app')
@section('title', $campaign->title)
@section('page-title', 'Campaign Details')
@push('styles')
    @vite('resources/css/features/rewards.css')
@endpush
@section('content')
<div class="ds-catalog">
    <x-ui.page-header :title="$campaign->title" lead="Approved USDT team business only. Viewing this page does not change qualification.">
        <x-slot:actions>
            <x-ui.button :href="route('member.rewards.index')" variant="secondary">All rewards</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Campaign">
        @if ($campaign->hasBanner())
            <img src="{{ route('member.rewards.image', $campaign) }}" alt="" class="ds-reward-detail-banner">
        @endif

        <dl class="ds-detail">
            <div><dt>Gift</dt><dd>{{ $campaign->gift_name }}</dd></div>
            <div><dt>Target</dt><dd>{{ \App\Support\MoneyDisplay::usdt($campaign->target_amount) }} approved team business</dd></div>
            <div><dt>Period</dt><dd>{{ $campaign->starts_on->format('d M Y') }} – {{ $campaign->ends_on->format('d M Y') }}</dd></div>
            <div><dt>Status</dt><dd><x-ui.badge :tone="$campaign->statusTone()">{{ $campaign->statusLabel() }}</x-ui.badge></dd></div>
        </dl>

        @if ($campaign->description)
            <p>{{ $campaign->description }}</p>
        @endif
        @if ($campaign->gift_description)
            <p><strong>Gift details:</strong> {{ $campaign->gift_description }}</p>
        @endif
        @if ($campaign->terms)
            <p><strong>Terms:</strong> {{ $campaign->terms }}</p>
        @endif
    </x-ui.card>

    @if ($campaign->hasVideo())
        <x-ui.card title="Campaign video">
            <video class="ds-reward-detail-video" controls preload="metadata" playsinline @if($campaign->hasBanner()) poster="{{ route('member.rewards.image', $campaign) }}" @endif>
                <source src="{{ route('member.rewards.video', $campaign) }}" type="video/mp4">
            </video>
        </x-ui.card>
    @endif

    <x-ui.card title="Your progress">
        <dl class="ds-detail">
            <div><dt>Achieved</dt><dd>{{ \App\Support\MoneyDisplay::usdt($achieved) }}</dd></div>
            <div><dt>Target</dt><dd>{{ \App\Support\MoneyDisplay::usdt($target) }}</dd></div>
            <div><dt>Remaining</dt><dd>{{ \App\Support\MoneyDisplay::usdt($remaining) }}</dd></div>
            <div><dt>Progress</dt><dd>{{ $percent }}%</dd></div>
            @if ($qualification)
                <div><dt>Stored status</dt><dd>{{ $qualification->status }}</dd></div>
            @else
                <div><dt>Stored status</dt><dd>Not evaluated yet</dd></div>
            @endif
        </dl>
        <p class="ds-pkg-hint">Progress uses approved USDT team business in the campaign window. A draft campaign does not qualify anyone.</p>
    </x-ui.card>
</div>
@endsection
