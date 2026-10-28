@extends('admin.layouts.app')
@section('title', 'Wallet Overview')
@section('page-title', 'Wallet Overview')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        title="Wallet Overview"
        lead="Wallet balances are USDT. Historical USD package purchases are not included in these totals. Crediting and payouts are not enabled."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.wallets.transactions') }}" variant="secondary">Transaction notes</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @foreach($notices as $notice)
        <x-ui.alert type="warning">{{ $notice }}</x-ui.alert>
    @endforeach
    <div class="ds-stat-grid">
        <a class="ds-stat-link" href="{{ route('admin.wallets.show', ['wallet' => 'roi']) }}">
            <x-ui.stat label="ROI Wallet" :value="\App\Support\MoneyDisplay::usdt($totals['roi'])" hint="Open member balances" />
        </a>
        <a class="ds-stat-link" href="{{ route('admin.wallets.show', ['wallet' => 'working']) }}">
            <x-ui.stat label="Working Wallet" :value="\App\Support\MoneyDisplay::usdt($totals['working'])" hint="Open member balances" />
        </a>
        <a class="ds-stat-link" href="{{ route('admin.wallets.show', ['wallet' => 'activation']) }}">
            <x-ui.stat label="Activation Wallet" :value="\App\Support\MoneyDisplay::usdt($totals['activation'])" hint="Open member balances" />
        </a>
        <x-ui.stat label="Stored records" :value="$roiCount.' ROI'" :hint="$levelCount.' level income'" />
    </div>
    <form class="ds-filter" method="GET">
        <label>
            <span>Member</span>
            <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search member ID or name">
        </label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            @if (request()->filled('q'))
                <a class="ds-btn ds-btn-secondary" href="{{ route('admin.wallets.overview') }}">Reset</a>
            @endif
        </div>
    </form>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Name</th>
                    <th class="ds-money">ROI Wallet</th>
                    <th class="ds-money">Working Wallet</th>
                    <th class="ds-money">Activation Wallet</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($member->roi_wallet_amount) }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($member->working_wallet_amount) }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="5">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
