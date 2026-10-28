@extends('admin.layouts.app')
@php
    $title = match ($wallet) {
        'roi' => 'ROI Wallet',
        'working' => 'Working Wallet',
        default => 'Activation Wallet',
    };
@endphp
@section('title', $title)
@section('page-title', $title)
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        :title="$title"
        lead="Read-only USDT balances. Crediting, ROI accrual, and payouts are not enabled."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.wallets.overview') }}" variant="secondary">Wallet Overview</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <form class="ds-filter" method="GET">
        <label>
            <span>Member</span>
            <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search member ID or name">
        </label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            @if (request()->filled('q'))
                <a class="ds-btn ds-btn-secondary" href="{{ route('admin.wallets.show', ['wallet' => $wallet]) }}">Reset</a>
            @endif
        </div>
    </form>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th class="ds-money">Balance</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }} @if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid'))<x-ui.badge tone="warning">Local test</x-ui.badge>@endif</td>
                    <td>
                        <x-ui.badge :tone="$member->status === 'active' ? 'success' : 'muted'">{{ ucfirst((string) $member->status) }}</x-ui.badge>
                    </td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($member->{$column}) }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="4">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
