@extends('admin.layouts.app')
@section('content')
@php
    $title = match ($wallet) {
        'roi' => 'ROI Wallet',
        'working' => 'Working Wallet',
        default => 'Activation Wallet',
    };
@endphp
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">{{ $title }}</h1>
        <p class="ds-page-copy">Read-only USDT balances. Crediting, ROI accrual, and payouts are not enabled.</p>
    </header>
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
                    <th class="p-3">Status</th>
                    <th class="p-3">Balance</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $member->member_id }}</td>
                    <td class="p-3">{{ $member->member_name }}</td>
                    <td class="p-3">{{ ucfirst((string) $member->status) }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::usdt($member->{$column}) }}</td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="4">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
