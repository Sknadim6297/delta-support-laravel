@extends('member.layouts.app')
@section('title', 'My Referral Eligibility')
@section('page-title', 'My Referral Eligibility')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Earnings"
        title="My Referral Eligibility"
        lead="Levels follow your qualifying direct members. Commission is not being credited."
        :heading="true"
    />

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-user-add-line" label="Direct referrals" :value="(string) $directCount" hint="Members you sponsored" />
        <x-ui.stat icon="ri-user-star-line" tone="teal" label="Qualifying directs" :value="(string) $qualifyingCount" hint="Approved Tour + ROI or Premium" />
        <x-ui.stat icon="ri-stack-line" tone="violet" label="Unlocked levels" :value="$unlockedLevels.' of 20'" hint="Based on qualifying directs" />
        <x-ui.stat icon="ri-shield-check-line" tone="green" label="Status" :value="$status" hint="Current eligibility state" />
    </div>

    <x-ui.card title="Your referral link" lead="1 qualifying direct unlocks 4 levels, 2 unlock 8, 3 unlock 12, 4 unlock 16, and 5 or more unlock 20. A direct counts once, after an approved Tour + ROI or Premium package.">
        <div class="pv-copy-row">
            <input type="text" readonly value="{{ $referralUrl }}" aria-label="Your referral link">
            <button type="button" class="ds-btn ds-btn-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
        </div>
    </x-ui.card>

    <x-ui.card title="Direct referrals" lead="Which directs currently qualify for unlock rules." flush>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Qualifying package</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($directs as $direct)
                    <tr>
                        <td>{{ $direct->member_id }}</td>
                        <td>{{ $direct->member_name }}</td>
                        <td>{{ in_array($direct->member_id, $qualifyingIds, true) ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td class="ds-empty-cell" colspan="3">No direct referrals yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ds-pagination">{{ $directs->links('pagination::bootstrap-5') }}</div>
    </x-ui.card>
</div>
@endsection
