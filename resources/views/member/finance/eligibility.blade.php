@extends('member.layouts.app')
@section('title', 'My Referral Eligibility')
@section('page-title', 'My Referral Eligibility')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Earnings"
        title="My Referral Eligibility"
        lead="Level Income unlock uses the 20-level commission system (separate from the 32-level rank ladder). Credits appear only after the daily financial job posts them."
        :heading="true"
    />

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-shield-user-line" tone="{{ $personallyActivated ? 'green' : 'muted' }}" label="Personal activation" :value="$personallyActivated ? 'Active' : 'Inactive'" hint="Qualifying approved package required" />
        <x-ui.stat icon="ri-user-add-line" label="Direct referrals" :value="(string) $directCount" hint="Members you sponsored" />
        <x-ui.stat icon="ri-user-star-line" tone="teal" label="Qualifying directs" :value="(string) $qualifyingCount" hint="Approved qualifying package" />
        <x-ui.stat icon="ri-stack-line" tone="violet" label="Unlocked levels" :value="$unlockedLevels.' of 20'" hint="Commission levels, not rank levels" />
        <x-ui.stat icon="ri-lock-line" tone="amber" label="Locked levels" :value="(string) max(0, 20 - (int) $unlockedLevels)" hint="Skipped (no spillover)" />
        <x-ui.stat icon="ri-funds-line" tone="green" label="Level Income credited" :value="\App\Support\MoneyDisplay::usdt($levelIncomeTotal ?? '0')" hint="Daily {{ \App\Support\MoneyDisplay::usdt($levelIncomeToday ?? '0') }}" />
    </div>

    <x-ui.alert type="info">
        <p>{{ $status }}</p>
        @if ($nextUnlock)
            <p>{{ $nextUnlock['message'] }}</p>
        @endif
    </x-ui.alert>

    <x-ui.card title="Level-wise commission percentages" lead="Applicable runtime chart for Level Income (20 levels). Locked levels receive no credit.">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Level</th>
                        <th class="ds-money">Rate</th>
                        <th>State</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rateChart as $level => $rate)
                    <tr>
                        <td>L{{ $level }}</td>
                        <td class="ds-money">{{ $rate }}%</td>
                        <td>
                            @if ($level <= $unlockedLevels)
                                <x-ui.badge tone="success">Unlocked</x-ui.badge>
                            @else
                                <x-ui.badge tone="muted">Locked</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Your referral link" lead="Progress depends on personal activation and qualifying directs under the applicable unlock configuration.">
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
