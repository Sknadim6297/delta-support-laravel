@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Referral Eligibility</h1>
        <p class="ds-page-copy">Qualifying directs are distinct sponsored members with an approved Tour + ROI or Premium package. This page does not pay commission.</p>
    </header>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search members" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2">
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Member ID</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">Direct referrals</th>
                    <th class="p-3">Qualifying directs</th>
                    <th class="p-3">Unlocked levels</th>
                    <th class="p-3">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                @php($summary = $summaries[$member->member_id] ?? ['direct_count' => 0, 'qualifying_direct_count' => 0, 'unlocked_levels' => 0, 'status' => 'No levels unlocked'])
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $member->member_id }}</td>
                    <td class="p-3">{{ $member->member_name }}</td>
                    <td class="p-3">{{ $summary['direct_count'] }}</td>
                    <td class="p-3">{{ $summary['qualifying_direct_count'] }}</td>
                    <td class="p-3">{{ $summary['unlocked_levels'] }} of 20</td>
                    <td class="p-3">{{ $summary['status'] }}</td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="6">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
