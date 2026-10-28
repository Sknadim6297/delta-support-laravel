@extends('admin.layouts.app')
@section('title', 'Income Scheduler Testing')
@section('page-title', 'Income Scheduler Testing')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        title="Income Scheduler Testing"
        lead="Local and testing only. This page previews eligibility and historical dates. It does not credit a wallet or backfill income."
    />
    @if ($message)
        <x-ui.alert type="success">{{ $message }}</x-ui.alert>
    @endif
    <x-ui.alert type="warning">
        @foreach ($report['missing_rules'] as $rule)
            <p>{{ $rule }}</p>
        @endforeach
    </x-ui.alert>
    <div class="ds-stat-grid">
        <x-ui.stat label="Scheduled tasks" :value="$report['scheduled_tasks']" hint="Package income is not scheduled" />
        <x-ui.stat label="Income-term snapshots" :value="$report['income_terms']" hint="Rate and duration rows" />
        <x-ui.stat label="Stored ROI records" :value="$report['ledger']['roi_records']" hint="Persisted rows only" />
        <x-ui.stat label="Stored level income" :value="$report['ledger']['level_records']" hint="Persisted rows only" />
    </div>
    <form class="ds-searchbar" method="POST" action="{{ route('admin.finance.income-test.preview') }}">
        @csrf
        <button type="submit" name="preview" value="roi">Preview ROI Calculation</button>
        <button type="submit" name="preview" value="level">Preview Level Commission</button>
        <button type="submit" name="preview" value="isolated">Run Test in Isolated Test Database</button>
        <a class="ds-btn ds-btn-secondary" href="{{ route('admin.finance.roi-history') }}">View ROI Test History</a>
        <a class="ds-btn ds-btn-secondary" href="{{ route('admin.finance.level-income') }}">View Level Income Test History</a>
    </form>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Approved package</th>
                    <th>ROI eligibility</th>
                    <th>Level income eligibility</th>
                    <th>Sponsor chain</th>
                    <th>ROI result</th>
                    <th>Level commission result</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($report['members'] as $member)
                <tr>
                    <td>
                        <span class="ds-person">
                            <strong>{{ $member['member_id'] }}</strong>
                            <small>{{ $member['name'] ?: 'Not found' }}</small>
                        </span>
                    </td>
                    <td>{{ $member['approved_packages'] === [] ? 'None' : implode('; ', $member['approved_packages']) }}</td>
                    <td><x-ui.badge :tone="$member['roi_eligible'] ? 'success' : 'muted'">{{ $member['roi_eligible'] ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    <td><x-ui.badge :tone="$member['level_eligible'] ? 'success' : 'muted'">{{ $member['level_eligible'] ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    <td>
                        {{ $member['sponsor_chain'] === [] ? 'No upline member' : implode(' → ', $member['sponsor_chain']) }}
                        @if ($member['sponsor_gap'])
                            <small>Missing {{ $member['sponsor_gap'] }}</small>
                        @endif
                    </td>
                    <td>
                        <x-ui.badge :tone="$member['roi']['status'] === 'blocked' ? 'warning' : 'muted'">{{ ucfirst($member['roi']['status']) }}</x-ui.badge>
                        <small>{{ $member['roi']['reason'] }}</small>
                    </td>
                    <td>
                        <x-ui.badge :tone="$member['level']['status'] === 'blocked' ? 'warning' : 'muted'">{{ ucfirst($member['level']['status']) }}</x-ui.badge>
                        <small>{{ $member['level']['reason'] }}</small>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-ui.table>
    <x-ui.card title="Historical accrual preview" subtitle="Original approval dates and stored purchase prices. Potential amounts are not ledger credits.">
        <x-ui.table>
            <table class="ds-table-wide">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Package</th>
                        <th class="ds-money">Stored price</th>
                        <th>Approved</th>
                        <th>First date</th>
                        <th>Last ROI</th>
                        <th>Last commission</th>
                        <th>ROI days</th>
                        <th class="ds-money">ROI amount</th>
                        <th>Commission days</th>
                        <th class="ds-money">Level amount</th>
                        <th>Blockers</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($history as $row)
                    <tr>
                        <td>{{ $row['member_id'] }}</td>
                        <td>{{ $row['package_name'] }}</td>
                        <td class="ds-money">{{ $row['purchase_price'] }} {{ $row['currency'] }}</td>
                        <td>{{ $row['approval_date'] ?: 'Not stored' }}</td>
                        <td>{{ $row['first_income_date'] ?: 'Not stored' }}</td>
                        <td>{{ $row['last_roi_date'] ?: 'Not stored' }}</td>
                        <td>{{ $row['last_commission_date'] ?: 'Not stored' }}</td>
                        <td>{{ $row['roi_elapsed_days'] }} elapsed, {{ $row['roi_credited_days'] }} credited, {{ $row['roi_missing_days'] }} missing</td>
                        <td class="ds-money">Potential {{ $row['roi_potential_amount'] }}. Credited {{ $row['roi_credited_amount'] }}.</td>
                        <td>{{ $row['commission_elapsed_days'] }} elapsed, {{ $row['commission_credited_days'] }} credited, {{ $row['commission_missing_days'] }} missing</td>
                        <td class="ds-money">Pool potential {{ $row['commission_potential_pool'] }}. Credited {{ $row['level_credited_amount'] }}.</td>
                        <td>{{ $row['blockers'] === [] ? 'None' : implode(' ', $row['blockers']) }}</td>
                    </tr>
                @empty
                    <tr><td class="ds-empty-cell" colspan="12">No package purchases are stored.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
