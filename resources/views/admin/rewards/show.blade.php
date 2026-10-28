@extends('admin.layouts.app')
@section('title', $campaign->title)
@section('page-title', $campaign->title)
@section('content')
<div class="ds-catalog">
    <x-ui.page-header :lead="$campaign->starts_on->format('d M Y').' – '.$campaign->ends_on->format('d M Y').' · Asia/Kolkata'">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.edit', $campaign)" variant="secondary">Edit</x-ui.button>
            <x-ui.button :href="route('admin.rewards.eligible', ['campaign_id' => $campaign->id])" variant="secondary">Eligible members</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('success'))
        <x-ui.alert>{{ session('success') }}</x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <div class="ds-stat-grid">
        <x-ui.stat label="Status" :value="$campaign->statusLabel()" />
        <x-ui.stat label="Target" :value="\App\Support\MoneyDisplay::usdt($campaign->target_amount)" />
        <x-ui.stat label="Qualified" :value="$campaign->qualified_count" hint="Qualified or selected" />
        <x-ui.stat label="Selected" :value="$campaign->selected_count" />
    </div>
    <x-ui.card title="Gift">
        @if ($campaign->gift_image_path)
            <img src="{{ route('admin.rewards.image', $campaign) }}" alt="" class="ds-package-thumb">
        @endif
        <p>{{ $campaign->gift_name }}</p>
        @if ($campaign->gift_description)
            <p>{{ $campaign->gift_description }}</p>
        @endif
        <p>{{ $campaign->stock_quantity === null ? 'No stock limit' : $campaign->stock_quantity.' in stock' }}</p>
    </x-ui.card>
    @if ($campaign->description)
        <x-ui.card title="Description"><p>{{ $campaign->description }}</p></x-ui.card>
    @endif
    @if ($campaign->terms)
        <x-ui.card title="Terms"><p>{{ $campaign->terms }}</p></x-ui.card>
    @endif
    @if ($campaign->admin_notes)
        <x-ui.card title="Internal notes"><p>{{ $campaign->admin_notes }}</p></x-ui.card>
    @endif
    <div class="ds-actions">
        @if (in_array($campaign->status, ['draft', 'scheduled', 'paused'], true))
            <form method="POST" action="{{ route('admin.rewards.publish', $campaign) }}">@csrf<x-ui.button type="submit">Publish</x-ui.button></form>
        @endif
        @if (in_array($campaign->status, ['active', 'scheduled'], true))
            <form method="POST" action="{{ route('admin.rewards.pause', $campaign) }}">@csrf<x-ui.button type="submit" variant="secondary">Pause</x-ui.button></form>
        @endif
        @if (in_array($campaign->status, ['active', 'paused'], true))
            <form method="POST" action="{{ route('admin.rewards.close', $campaign) }}">@csrf<x-ui.button type="submit" variant="secondary">Close</x-ui.button></form>
        @endif
        @if (in_array($campaign->status, ['draft', 'scheduled', 'active', 'paused'], true))
            <form method="POST" action="{{ route('admin.rewards.cancel', $campaign) }}">@csrf<x-ui.button type="submit" variant="danger">Cancel</x-ui.button></form>
        @endif
        @if (in_array($campaign->status, ['active', 'completed'], true))
            <form method="POST" action="{{ route('admin.rewards.evaluate', $campaign) }}">@csrf<x-ui.button type="submit" variant="outline">Recalculate</x-ui.button></form>
        @endif
    </div>
</div>
@endsection
