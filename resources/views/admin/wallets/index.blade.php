@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-2 text-2xl font-bold text-navy-900">{{ $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet' }}</h1>
    <p class="mb-4 text-sm text-slate-600">These are the balances already stored on member records. Crediting, ROI accrual, and payouts are not enabled.</p>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Member</th><th class="p-3">Name</th><th class="p-3">Status</th><th class="p-3">Balance</th></tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr class="border-t"><td class="p-3">{{ $member->member_id }}</td><td class="p-3">{{ $member->member_name }}</td><td class="p-3">{{ $member->status }}</td><td class="p-3">{{ $member->{$column} }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-6 text-slate-500">No members yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
