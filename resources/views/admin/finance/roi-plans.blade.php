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
</div>
@endsection
