@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-brand-600">Delta Support</p>
            <h1 class="ds-page-title mt-1">Dashboard</h1>
            <p class="mt-1 text-sm text-ink-500">Live member counts from this database. Package sales are priced in USD. ROI, level income, and withdrawals are not being credited.</p>
        </div>
        <x-ui.badge>Development</x-ui.badge>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat label="Members" :value="$memberCount" hint="All member records" />
        <x-ui.stat label="Active members" :value="$activeMembers" />
        <x-ui.stat label="Inactive members" :value="$inactiveMembers" />
        <x-ui.stat label="Activation wallet" :value="'$'.number_format($activationWalletTotal, 2)" hint="Sum of stored balances" />
        <x-ui.stat label="Working wallet" :value="number_format($workingWalletTotal, 2).' USDT'" hint="Sum of stored balances" />
        <x-ui.stat label="ROI wallet" :value="number_format($roiWalletTotal, 2)" hint="Sum of stored balances" />
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
