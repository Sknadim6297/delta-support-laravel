@extends('admin.layouts.app')
@section('title', 'ROI Plans')
@section('page-title', 'ROI Plans')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="ROI Management"
        title="ROI Plans"
        lead="No ROI percentage is saved on a package row. Daily USDT amounts come from package_income config. Legacy commission_pool and Associate Commission are shown in separate columns. This page does not credit ROI or associate commission."
    />
    <div class="ds-stat-grid">
        <x-ui.stat label="Packages" :value="$packages->count()" hint="Saved package records" />
        <x-ui.stat label="Income-term snapshots" :value="$incomeTermCount" hint="Stored snapshots only" />
        <x-ui.stat label="Associate version" :value="$associatePreview['version_code'] ?? '—'" hint="{{ ($associatePreview['posting_enabled'] ?? false) ? 'Posting ENABLED' : 'Posting disabled' }}" />
    </div>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th class="ds-money">Price</th>
                    <th>ROI flag</th>
                    <th>Level income flag</th>
                    <th class="ds-money">Daily ROI</th>
                    <th>ROI days</th>
                    <th class="ds-money">Legacy commission pool</th>
                    <th>Legacy commission days</th>
                    <th class="ds-money">Associate commission pool</th>
                    <th>Associate days / max</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                @php
                    $rule = $rules[$package->code] ?? null;
                    $tier = $associateByCode[$package->code] ?? null;
                @endphp
                <tr>
                    <td>{{ $package->name }}</td>
                    <td>{{ $package->code }}</td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td><x-ui.badge :tone="$package->roi_eligible ? 'success' : 'muted'">{{ $package->roi_eligible ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    <td><x-ui.badge :tone="$package->level_commission_eligible ? 'success' : 'muted'">{{ $package->level_commission_eligible ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    <td class="ds-money">{{ $rule ? $rule['roi_daily'].' USDT' : 'Not configured' }}</td>
                    <td>{{ $rule ? $rule['roi_days'] : 'Not configured' }}</td>
                    <td class="ds-money">{{ $rule && $rule['commission_pool'] !== null ? $rule['commission_pool'].' USDT' : 'Legacy commission Not configured' }}</td>
                    <td>{{ $rule && $rule['commission_days'] ? $rule['commission_days'] : 'Legacy commission Not configured' }}</td>
                    <td class="ds-money">
                        @if ($tier)
                            {{ $tier['daily_total'] }} USDT
                            <div>{{ $tier['label'] }} · {{ $associatePreview['version_code'] }}</div>
                        @else
                            Associate commission Not configured
                        @endif
                    </td>
                    <td>
                        @if ($tier)
                            {{ $tier['commission_days'] }} days · max {{ $tier['duration_total'] }} USDT
                        @else
                            Associate commission Not configured
                        @endif
                    </td>
                    <td><x-ui.badge :tone="$package->is_active ? 'success' : 'muted'">{{ $package->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="13">No packages have been saved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>

    <x-ui.card
        title="Original Tour Plan Comparison (PROPOSED)"
        :subtitle="'Version '.($originalPlan['version_code'] ?? 'original_tour_plan_v1').' · inactive · does not credit wallets'"
    >
        <x-ui.alert type="warning">
            {{ $originalPlan['label'] ?? 'PROPOSED' }}.
            Conversion basis: INR {{ $originalPlan['conversion']['inr_per_usdt'] ?? '104' }} = 1 USDT
            ({{ $originalPlan['conversion']['basis_label'] ?? 'proposed fixed ratio' }}).
            Rounding policy: {{ $originalPlan['conversion']['rounding_policy'] ?? 'unapproved' }}.
            Active engine remains package_income. Historical ROI is unchanged.
        </x-ui.alert>

        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tier</th>
                        <th>Proposed code</th>
                        <th>USDT price</th>
                        <th>Existing ROI</th>
                        <th>Proposed INR daily</th>
                        <th>Proposed USDT daily (exact / 4dp floor / 4dp round)</th>
                        <th>Proposed days</th>
                        <th>Duration totals</th>
                        <th>Tour benefits (PROPOSED)</th>
                        <th>Differences</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($originalPlan['packages'] as $row)
                    <tr>
                        <td>
                            {{ $row['tier'] }}
                            <div><x-ui.badge tone="muted">{{ $row['status_label'] }}</x-ui.badge></div>
                        </td>
                        <td>{{ $row['proposed_package_code'] }}</td>
                        <td class="ds-money">{{ $row['existing_usdt_price'] }} USDT</td>
                        <td>
                            @if ($row['existing_roi'])
                                {{ $row['existing_roi']['roi_daily'] }} × {{ $row['existing_roi']['roi_days'] }} days
                                <div>Total {{ $row['existing_roi']['duration_total'] }} USDT</div>
                            @else
                                Not configured in package_income
                            @endif
                        </td>
                        <td>
                            @if ($row['has_daily_roi'])
                                INR {{ $row['original_inr_daily'] }}
                            @else
                                No daily ROI
                            @endif
                        </td>
                        <td>
                            @if ($row['proposed_conversion'])
                                Exact {{ $row['proposed_conversion']['exact_usdt_daily_12dp'] }}
                                <div>Floor {{ $row['proposed_conversion']['proposed_usdt_daily_4dp_floor'] }}</div>
                                <div>Round {{ $row['proposed_conversion']['proposed_usdt_daily_4dp_round'] }}</div>
                                <div>Candidate {{ $row['proposed_usdt_daily'] ?? $row['proposed_conversion']['proposed_usdt_daily_candidate'] }}</div>
                                <div>Floor vs round {{ $row['proposed_conversion']['floor_vs_round_diff'] }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['proposed_roi_days'] ?? '—' }}</td>
                        <td>
                            @if ($row['proposed_conversion'])
                                Advertised INR {{ $row['proposed_conversion']['advertised_inr_total'] }}
                                <div>Floor × days {{ $row['proposed_conversion']['math_daily_x_days_floor'] }}</div>
                                <div>Round × days {{ $row['proposed_conversion']['math_daily_x_days_round'] }}</div>
                                <div>Candidate × days {{ $row['proposed_conversion']['math_daily_x_days_candidate'] }}</div>
                                <div>Final-day adj (floor) {{ $row['proposed_conversion']['final_day_adjustment_floor'] }}</div>
                                <div>Final-day adj (round) {{ $row['proposed_conversion']['final_day_adjustment_round'] }}</div>
                                <div>Final-day adj (candidate) {{ $row['proposed_conversion']['final_day_adjustment_candidate'] }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['tour']['summary'] }}</td>
                        <td>
                            @foreach ($row['differences'] as $diff)
                                <div>{{ $diff }}</div>
                            @endforeach
                            @foreach (($row['chart_notes'] ?? []) as $note)
                                <div>{{ $note }}</div>
                            @endforeach
                            @if (! empty($row['notes']))
                                <div>{{ $row['notes'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <h3 style="margin-top: 1rem;">Associate commission (unchanged)</h3>
        <p>
            associate_v1 pools SILVER {{ $originalPlan['associate_reminder']['pools']['SILVER'] }},
            GOLD {{ $originalPlan['associate_reminder']['pools']['GOLD'] }},
            PREMIUM {{ $originalPlan['associate_reminder']['pools']['PREMIUM'] }},
            VIP {{ $originalPlan['associate_reminder']['pools']['VIP'] }}
            for {{ $originalPlan['associate_reminder']['commission_days'] }} days.
            DIAMOND: {{ $originalPlan['associate_reminder']['diamond'] }}.
            Posting: {{ $originalPlan['associate_reminder']['posting_enabled'] ? 'ENABLED' : 'disabled' }}.
            Snapshot: {{ $originalPlan['associate_reminder']['snapshot_on_approval'] ? 'on' : 'disabled' }}.
        </p>

        <h3>Outstanding approvals</h3>
        <ul>
            @foreach ($originalPlan['approval_questions'] as $question)
                <li>{{ $question }}</li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
@endsection
