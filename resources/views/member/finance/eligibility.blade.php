@extends('member.layouts.app')
@section('title', 'My Referral Eligibility')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">My Referral Eligibility</h1>
        <p class="ds-page-copy">Levels follow your qualifying direct members. Commission is not being credited.</p>
    </header>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Direct referrals</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $directCount }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Qualifying directs</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $qualifyingCount }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Unlocked levels</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $unlockedLevels }} of 20</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Status</p><p class="mt-2 text-base font-semibold text-navy-900">{{ $status }}</p></article>
    </div>
    <section class="ds-card mb-4">
        <h2 class="mb-2 text-base font-semibold text-navy-900">Your referral link</h2>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input type="text" class="ds-control min-w-0 flex-1" readonly value="{{ $referralUrl }}" aria-label="Your referral link">
            <button type="button" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
        </div>
        <p class="mt-3 text-sm text-ink-500">1 qualifying direct unlocks 4 levels, 2 unlock 8, 3 unlock 12, 4 unlock 16, and 5 or more unlock 20. A direct counts once, after an approved Tour + ROI or Premium package.</p>
    </section>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Member ID</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">Qualifying package</th>
                </tr>
            </thead>
            <tbody>
            @forelse($directs as $direct)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $direct->member_id }}</td>
                    <td class="p-3">{{ $direct->member_name }}</td>
                    <td class="p-3">{{ in_array($direct->member_id, $qualifyingIds, true) ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="3">No direct referrals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $directs->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
