@extends('admin.layouts.app')
@section('title', 'ROI Plans')
@section('page-title', 'ROI Plans')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="ROI Management"
        title="ROI Plans"
        lead="No ROI percentage is saved on a package. Coastal Weekend and Hill Station Retreat use the configured daily USDT amounts. This page does not credit ROI."
    />
    <div class="ds-stat-grid">
        <x-ui.stat label="Packages" :value="$packages->count()" hint="Saved package records" />
        <x-ui.stat label="Income-term snapshots" :value="$incomeTermCount" hint="Stored snapshots only" />
    </div>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Type</th>
                    <th class="ds-money">Price</th>
                    <th>ROI flag</th>
                    <th>Level income flag</th>
                    <th class="ds-money">Daily ROI</th>
                    <th>ROI days</th>
                    <th class="ds-money">Commission pool</th>
                    <th>Commission days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                <tr>
                    <td>{{ $package->name }}</td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td><x-ui.badge :tone="$package->roi_eligible ? 'success' : 'muted'">{{ $package->roi_eligible ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    <td><x-ui.badge :tone="$package->level_commission_eligible ? 'success' : 'muted'">{{ $package->level_commission_eligible ? 'Eligible' : 'Not eligible' }}</x-ui.badge></td>
                    @php($rule = $rules[$package->code] ?? null)
                    <td class="ds-money">{{ $rule ? $rule['roi_daily'].' USDT' : 'Not stored' }}</td>
                    <td>{{ $rule ? $rule['roi_days'] : 'Not stored' }}</td>
                    <td class="ds-money">{{ $rule ? $rule['commission_pool'].' USDT' : 'Not stored' }}</td>
                    <td>{{ $rule && $rule['commission_days'] ? $rule['commission_days'] : 'Not stored' }}</td>
                    <td><x-ui.badge :tone="$package->is_active ? 'success' : 'muted'">{{ $package->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="10">No packages have been saved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
</div>
@endsection
