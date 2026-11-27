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
        <p class="mb-0"><strong>Applicable rule:</strong> {{ $ruleMode ?? 'legacy' }}
            @if (!empty($applicableNote))
                — {{ $applicableNote }}
            @endif
        </p>
        @if (!empty($eligibilityContext) && is_array($eligibilityContext))
            <p class="mb-0"><strong>Eligibility context:</strong> {{ $eligibilityContext['kind'] ?? 'n/a' }}
                @if (!empty($eligibilityContext['source_purchase_id']))
                    (source purchase #{{ $eligibilityContext['source_purchase_id'] }}, business date {{ $eligibilityContext['business_date'] ?? 'n/a' }})
                @endif
                — {{ $eligibilityContext['note'] ?? '' }}
            </p>
        @endif
        @if (($legacyUnlockedLevels ?? null) !== null && (int) $legacyUnlockedLevels !== (int) $unlockedLevels)
            <p class="mb-0">Legacy ladder unlock: {{ (int) $legacyUnlockedLevels }} of 20 (for comparison only).</p>
        @endif
        @if (($authorizedPreviewUnlockedLevels ?? null) !== null && (string) ($ruleMode ?? '') !== 'authorized')
            <p class="mb-0">Proposed-band preview (not posting-authorized): {{ (int) $authorizedPreviewUnlockedLevels }} of 20.
                @if (($protectedPreviewUnlockedLevels ?? null) !== null)
                    Protected preview: {{ (int) $protectedPreviewUnlockedLevels }} of 20.
                @endif
            </p>
        @endif
        @if ($nextUnlock)
            <p>{{ $nextUnlock['message'] }}</p>
        @endif
        <p class="mb-0">
            @if (($ruleMode ?? 'legacy') === 'authorized')
                Live unlock uses the Admin-authorized rule version (including combined joining + purchase when configured). Unlocked levels are not the same as commission payment eligibility.
            @else
                Live unlock uses income-eligible unique directs until Admin authorizes a new version.
            @endif
            Tour Only Total Business remains separate from Level Income generation.
        </p>
    </x-ui.alert>

    @if (!empty($qualificationBreakdown) && is_array($qualificationBreakdown))
        <x-ui.card title="Qualification breakdown" lead="{{ ($ruleMode ?? 'legacy') === 'authorized' ? 'Joining and purchase contributions under the live authorized version settings.' : 'Joining and purchase contributions under the preview/authorized version settings. Live legacy may still apply until posting is authorized.' }}">
            <div class="ds-stat-grid">
                <x-ui.stat label="Mode" :value="(string) ($qualificationBreakdown['mode'] ?? 'single_basis')" />
                <x-ui.stat label="Joining metric" :value="(string) ($qualificationBreakdown['joining_weighted'] ?? 0)" hint="{{ !empty($qualificationBreakdown['joining_enabled']) ? 'enabled' : 'off' }}" />
                <x-ui.stat label="Purchase metric" :value="(string) ($qualificationBreakdown['purchase_weighted'] ?? 0)" hint="{{ !empty($qualificationBreakdown['purchase_enabled']) ? 'enabled' : 'off' }}" />
                <x-ui.stat label="Combined total" :value="(string) ($qualificationBreakdown['total_metric'] ?? 0)" />
            </div>
            <p class="ds-muted" style="margin:.75rem 0 0;">{{ $qualificationBreakdown['formula_note'] ?? '' }}</p>
            @if (!empty($qualificationBreakdown['double_credit_policy']))
                <p class="ds-muted" style="margin:.35rem 0 0;">Double-credit policy: {{ str_replace('_', ' ', (string) $qualificationBreakdown['double_credit_policy']) }}
                    @if (!empty($qualificationBreakdown['overlapping_directs']))
                        · overlapping directs: {{ (int) $qualificationBreakdown['overlapping_directs'] }}
                    @endif
                </p>
            @endif
        </x-ui.card>
    @endif

    @if (!empty($qualificationPreview) && is_array($qualificationPreview))
        <x-ui.card title="Qualification preview" lead="Read-only comparison from existing registrations and approved purchases. Does not change live posting.">
            <div class="ds-stat-grid">
                <x-ui.stat label="Registered directs" :value="(string) ($qualificationPreview['registered_direct_count'] ?? 0)" />
                <x-ui.stat label="Approved purchasing directs" :value="(string) ($qualificationPreview['approved_package_direct_count'] ?? 0)" />
                <x-ui.stat label="Own approved purchases" :value="(string) ($qualificationPreview['personal_approved_purchase_count'] ?? 0)" hint="Personal package rows for Option B" />
                <x-ui.stat label="Direct package purchases" :value="(string) ($qualificationPreview['approved_direct_package_purchase_count'] ?? 0)" />
                <x-ui.stat label="Tour Only purchasing directs" :value="(string) ($qualificationPreview['tour_only_purchasing_direct_count'] ?? 0)" />
                <x-ui.stat label="Currently unlocked" :value="(string) ($qualificationPreview['currently_unlocked_levels'] ?? 0).' of 20'" hint="{{ ($qualificationPreview['live_rule_mode'] ?? 'legacy').' / '.($qualificationPreview['applicable_version'] ?? 'legacy') }}" />
                <x-ui.stat label="Legacy comparison" :value="(string) ($qualificationPreview['legacy_unlocked_levels'] ?? $qualificationPreview['currently_unlocked_levels'] ?? 0).' of 20'" hint="Hard-coded legacy ladder only" />
                <x-ui.stat label="Candidate expected" :value="(string) (($qualificationPreview['candidate_expected']['expected_unlocked_levels'] ?? 0)).' of 20'" hint="{{ $qualificationPreview['candidate_expected']['version_code'] ?? 'n/a' }}" />
            </div>
            <p class="ds-muted" style="margin:.75rem 0;">{{ $qualificationPreview['locked_reason'] ?? '' }}</p>
            @if (!empty($qualificationPreview['proposed_delta_reason']))
                <p class="ds-muted" style="margin:.75rem 0;">{{ $qualificationPreview['proposed_delta_reason'] }}</p>
            @endif
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th>Tour Only unlock</th>
                            <th>Metric</th>
                            <th>Expected levels</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach (($qualificationPreview['expected_under_each_mode'] ?? []) as $modeRow)
                        <tr>
                            <td>{{ str_replace('_', ' ', (string) ($modeRow['basis'] ?? '')) }}</td>
                            <td>{{ !empty($modeRow['tour_only_qualifies_for_unlock']) ? 'Yes' : 'No' }}</td>
                            <td>{{ (int) ($modeRow['direct_metric'] ?? 0) }}</td>
                            <td>{{ (int) ($modeRow['expected_unlocked_levels'] ?? 0) }} of 20</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </x-ui.table>
        </x-ui.card>
    @endif

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
