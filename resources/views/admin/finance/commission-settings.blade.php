@extends('admin.layouts.app')
@section('title', 'Commission Settings')
@section('page-title', 'Commission Settings')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Commission Settings"
        lead="Legacy percentage plan stays inactive. The Associate Commission Structure stores fixed USDT/day ranks for Silver/Gold/Premium/VIP over 400 days. This page does not generate or credit level income."
    />
    <div class="ds-stat-grid">
        <x-ui.stat label="Levels stored" :value="$levels->count()" hint="Rows in the stored percentage plan" />
        <x-ui.stat label="Combined rate" :value="$total.'%'" hint="Sum of stored percentages" />
        <x-ui.stat label="Percentage plan" :value="$active ? 'Active' : 'Inactive'" hint="Inactive plans do not pay commission" />
        <x-ui.stat label="Associate version" :value="$associate['version_code']" hint="{{ $associate['is_active'] ? 'Marked active' : 'Stored inactive' }}" />
        <x-ui.stat label="Associate engine" :value="($associateEngine['mode'] ?? 'dry_run_only')" hint="Posting {{ ($associateEngine['posting_enabled'] ?? false) ? 'ENABLED' : 'disabled' }} · Snapshot {{ ($associateEngine['snapshot_on_approval'] ?? false) ? 'on' : 'disabled' }}" />
    </div>
    @if ($errors !== [])
        <x-ui.alert type="warning">
            @foreach ($errors as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif
    @if (($associate['integrity_errors'] ?? []) !== [])
        <x-ui.alert type="warning">
            @foreach ($associate['integrity_errors'] as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @else
        <x-ui.alert type="info">
            {{ $associate['interpretation'] }}
            Posting enabled: {{ $associate['posting_enabled'] ? 'yes' : 'no' }}.
            Snapshot on approval: {{ $associate['snapshot_on_approval'] ? 'yes' : 'no' }}.
            Theoretical totals are not earned/posted amounts.
            Calculation engine: {{ $associateEngine['name'] ?? 'associate_commission_calculator_v1' }} ({{ $associateEngine['mode'] ?? 'dry_run_only' }}; never mutates wallets/ledgers).
        </x-ui.alert>
    @endif

    <x-ui.card title="Associate unlock thresholds (read-only)" subtitle="Existing ReferralLevelEligibility rules. Qualifying directs unlock ranks; directs ≠ unlocked levels.">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Qualifying direct referrals</th>
                        <th>Unlocked ranks</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($unlockRules as $rule)
                    <tr>
                        <td>{{ $rule['directs'] }}</td>
                        <td>{{ $rule['levels'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
        <p>Eligibility is evaluated at calculation time (same as legacy level accrual). Associate posting remains disabled.</p>
    </x-ui.card>

    <x-ui.card title="Associate Commission Structure" :subtitle="'Version '.$associate['version_code'].' · '.$associate['commission_days'].' days · read-only preview'">
        @foreach ($associate['tiers'] as $tier)
            <h3 style="margin: 1rem 0 0.5rem;">{{ $tier['label'] }} · {{ $tier['package_price'] }} USDT</h3>
            <p>
                Mapped code: {{ $tier['mapped_package_code'] ?: 'Unmapped' }}
                ({{ $tier['catalog_package_found'] ? 'catalog configured' : 'catalog pending' }}).
                Daily total {{ $tier['daily_total'] }} USDT.
                Max 400-day total {{ $tier['duration_total'] }} USDT (theoretical — not posted income).
                ROI: {{ $tier['roi_display'] }}.
                Snapshot: {{ $associate['snapshot_on_approval'] ? 'flag on' : 'disabled' }}.
                Posting: {{ $associate['posting_enabled'] ? 'ENABLED' : 'disabled' }}.
            </p>
            @if ($tier['warnings'] !== [])
                <ul>
                    @foreach ($tier['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th class="ds-money">Daily USDT</th>
                            <th class="ds-money">400-day USDT</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($tier['ranks'] as $rank)
                        <tr>
                            <td>{{ $rank['rank'] }}</td>
                            <td class="ds-money">{{ $rank['daily_amount'] }}</td>
                            <td class="ds-money">{{ $rank['duration_amount'] }}</td>
                        </tr>
                    @endforeach
                        <tr>
                            <td>Total</td>
                            <td class="ds-money">{{ $tier['daily_total'] }}</td>
                            <td class="ds-money">{{ $tier['duration_total'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </x-ui.table>
        @endforeach
    </x-ui.card>

    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Level</th>
                    <th class="ds-money">Percentage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($levels as $level)
                <tr>
                    <td>{{ $level->level }}</td>
                    <td class="ds-money">{{ $level->percentage }}%</td>
                    <td><x-ui.badge :tone="$level->is_active ? 'success' : 'muted'">{{ $level->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="3">No commission plan is stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    @foreach ($pools as $pool)
        <x-ui.card :title="$pool['name'].' legacy daily distribution'" subtitle="Pool {{ $pool['pool'] }} USDT for {{ $pool['days'] }} days. Full pool {{ $pool['duration_total'] }} USDT. Actual payouts still require an eligible sponsor and unlocked levels. Generation is disabled.">
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th class="ds-money">Rate</th>
                            <th class="ds-money">Daily USDT</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($pool['levels'] as $level)
                        <tr>
                            <td>{{ $level['level'] }}</td>
                            <td class="ds-money">{{ $level['rate'] }}%</td>
                            <td class="ds-money">{{ $level['amount'] }}</td>
                        </tr>
                    @endforeach
                        <tr>
                            <td>Total</td>
                            <td class="ds-money">100.0000%</td>
                            <td class="ds-money">{{ $pool['sum'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </x-ui.table>
        </x-ui.card>
    @endforeach
</div>
@endsection
