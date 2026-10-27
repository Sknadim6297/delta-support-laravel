@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Wallet Overview</h1>
        <p class="ds-page-copy">Wallet balances are USDT. Historical USD package purchases are not included in these totals. Crediting and payouts are not enabled.</p>
    </header>
    <div class="mb-4 space-y-2">
        @foreach($notices as $notice)
            <p class="ds-legacy-lock" role="status">{{ $notice }}</p>
        @endforeach
    </div>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">ROI Wallet</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($totals['roi']) }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Working Wallet</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($totals['working']) }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Activation Wallet</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($totals['activation']) }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Stored records</p><p class="mt-2 text-sm text-navy-900">{{ $roiCount }} ROI · {{ $levelCount }} level income</p></article>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search member ID or name" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2">
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Member</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">ROI Wallet</th>
                    <th class="p-3">Working Wallet</th>
                    <th class="p-3">Activation Wallet</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $member->member_id }}</td>
                    <td class="p-3">{{ $member->member_name }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::usdt($member->roi_wallet_amount) }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::usdt($member->working_wallet_amount) }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="5">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
