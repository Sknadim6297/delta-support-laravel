@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-brand-600">Delta Support</p>
            <h1 class="ds-page-title mt-1">Dashboard</h1>
            <p class="mt-1 text-sm text-ink-500">New package sales are priced in USDT. Historical USD purchases stay in their own total. ROI, level income, and withdrawals are not being credited.</p>
        </div>
        <x-ui.badge>Development</x-ui.badge>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat label="Members" :value="$memberCount" hint="All member records" />
        <x-ui.stat label="Active members" :value="$activeMembers" />
        <x-ui.stat label="Inactive members" :value="$inactiveMembers" />
        <x-ui.stat label="Approved payments (USDT)" :value="\App\Support\MoneyDisplay::usdt($approvedPurchases['USDT'])" hint="USDT purchases only" />
        <x-ui.stat label="Approved payments (USD)" :value="\App\Support\MoneyDisplay::format($approvedPurchases['USD'], 'USD')" hint="Historical USD purchases only" />
        <x-ui.stat label="Pending payments (USDT)" :value="\App\Support\MoneyDisplay::usdt($pendingPurchases['USDT'])" hint="USDT purchases only" />
        <x-ui.stat label="Pending payments (USD)" :value="\App\Support\MoneyDisplay::format($pendingPurchases['USD'], 'USD')" hint="Historical USD purchases only" />
        <x-ui.stat label="Activation wallet" :value="\App\Support\MoneyDisplay::usdt($activationWalletTotal)" hint="USDT balance" />
        <x-ui.stat label="Working wallet" :value="\App\Support\MoneyDisplay::usdt($workingWalletTotal)" hint="USDT balance" />
        <x-ui.stat label="ROI wallet" :value="\App\Support\MoneyDisplay::usdt($roiWalletTotal)" hint="USDT balance" />
    </div>

    <div class="mt-6">
        @if ($memberCount === 0)
            <x-ui.empty title="No members yet" message="Registration and admin member tools remain available. This dashboard will fill in as real records are created." />
        @else
            <x-ui.card title="Workspace" subtitle="Existing modules stay on their current routes.">
                <div class="flex flex-wrap gap-2">
                    <x-ui.button :href="route('admin.members.directory')" variant="secondary">All members</x-ui.button>
                    <x-ui.button :href="route('admin.packages.index')" variant="secondary">Packages</x-ui.button>
                    <x-ui.button :href="route('admin.payments.index', ['status' => 'pending'])" variant="secondary">Pending payments</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    </div>
@endsection
