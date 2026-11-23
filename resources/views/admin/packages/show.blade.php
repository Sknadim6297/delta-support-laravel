@extends('admin.layouts.app')
@section('title', 'Package Details')
@section('page-title', 'Package Details')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header :title="$package->name" :lead="$package->code.' · '.$package->typeLabel()">
        <x-slot:actions>
            <x-ui.button :href="route('admin.packages.edit', $package)">Edit</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.packages.index')">All packages</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <x-ui.stat label="Catalog" :value="$status['catalog_active'] ? 'Active' : 'Inactive'" :hint="$status['purchase_available'] ? 'Purchase available' : 'Purchase unavailable'" />
        <x-ui.stat label="ROI flag" :value="$status['roi_eligible'] ? 'Eligible' : 'Not eligible'" :hint="$status['income_configured'] ? 'Income configured' : 'Income not configured'" />
        <x-ui.stat label="Income source" :value="$status['income_source']" :hint="'Accrual authority: '.($status['accrual_authority'] ? 'yes' : 'no')" />
        <x-ui.stat label="Posting" :value="$status['posting_authorized'] ? 'Authorized flags on' : 'Not authorized'" :hint="$status['posting_note']" />
    </div>

    <x-ui.alert type="info">
        Associate posting: {{ ($status['associate_posting'] ?? false) ? 'ENABLED' : 'disabled' }}.
        This screen never credits wallets.
    </x-ui.alert>

    <x-ui.card title="Catalog" subtitle="Stored package row">
        <div class="ds-field-grid">
            <div><strong>Code</strong><div>{{ $package->code }}</div></div>
            <div><strong>Price</strong><div>{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</div></div>
            <div><strong>Type</strong><div>{{ $package->typeLabel() }}</div></div>
            <div><strong>Purchases</strong><div>{{ $package->purchases()->count() }}</div></div>
            <div class="ds-field-span"><strong>Description</strong><div>{{ $package->description ?: '—' }}</div></div>
        </div>
        <form method="POST" action="{{ route('admin.packages.status', $package) }}" style="margin-top:1rem;" onsubmit="return confirm('Confirm catalog status change? This does not authorize income posting.');">
            @csrf
            @method('PATCH')
            <input type="hidden" name="is_active" value="{{ $package->is_active ? 0 : 1 }}">
            <x-ui.button type="submit" variant="secondary">{{ $package->is_active ? 'Deactivate package' : 'Activate package' }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Effective income definition" subtitle="Read-only resolver output used by the Daily ROI engine adapter">
        @if ($incomeRule)
            <p>Daily ROI {{ $incomeRule['roi_daily'] }} USDT × {{ $incomeRule['roi_days'] }} days = {{ bcmul($incomeRule['roi_daily'], (string) $incomeRule['roi_days'], 4) }} USDT</p>
            <p>Source: {{ $incomeRule['source'] ?? 'unknown' }}</p>
            <p>
                Plan/legacy commission pool (package_income / plan version):
                @if (($incomeRule['commission_pool'] ?? null) !== null)
                    {{ $incomeRule['commission_pool'] }} USDT / {{ $incomeRule['commission_days'] ?? '—' }} days
                @else
                    Not configured
                @endif
            </p>
            <p class="ds-pkg-hint">
                Associate commission is separate (associate_v1 by package code). It is not the plan/legacy pool above.
            </p>
        @else
            <p>ROI Not Configured for accrual resolution.</p>
        @endif
        @if ($plan)
            <p>Active plan version: v{{ $plan->version_number }} ({{ $plan->status }}) · accrual_authority={{ $plan->accrual_authority ? 'yes' : 'no' }}</p>
        @endif
        @if ($associateTier)
            <p>
                Associate {{ $associateTier['label'] }}:
                {{ $associateTier['daily_total'] }} USDT / {{ $associateTier['commission_days'] }} days
                (max {{ $associateTier['duration_total'] }} USDT theoretical) ·
                posting {{ ($associatePreview['posting_enabled'] ?? false) ? 'ENABLED' : 'disabled' }}
            </p>
        @endif
    </x-ui.card>

    <x-ui.card title="Version history">
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ver</th>
                        <th>Status</th>
                        <th>Daily</th>
                        <th>Days</th>
                        <th>Total</th>
                        <th>Tour wait</th>
                        <th>Auth</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($versions as $version)
                    <tr>
                        <td>v{{ $version->version_number }}</td>
                        <td>{{ $version->status }}</td>
                        <td>{{ $version->has_daily_roi ? $version->roi_daily : '—' }}</td>
                        <td>{{ $version->roi_days ?? '—' }}</td>
                        <td>{{ $version->totalRoi() ?? '—' }}</td>
                        <td>{{ $version->tour_eligible_after_days ?? '—' }}</td>
                        <td>{{ $version->accrual_authority ? 'yes' : 'no' }}</td>
                        <td>{{ $version->change_note ?: ($version->label ?: '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ds-empty-cell">No plan versions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card title="Audit history">
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($audits as $audit)
                    <tr>
                        <td>{{ $audit->created_at }}</td>
                        <td>{{ $audit->action }}</td>
                        <td>{{ $audit->user_id }}</td>
                        <td>{{ $audit->package_plan_version_id ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ds-empty-cell">No audits yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
@endsection
