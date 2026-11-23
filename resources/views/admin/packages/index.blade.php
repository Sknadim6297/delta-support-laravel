@extends('admin.layouts.app')
@section('title', 'All Packages')
@section('page-title', 'All Packages')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header title="Package Management" lead="Create and edit packages from Admin. Catalog Active is not wallet posting. Purchases keep immutable snapshots. Associate ranks stay preview-only.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.packages.create')">Add Package</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.finance.commission-settings')">Associate commission chart</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" aria-label="Search name or code">
        <select name="status" aria-label="Package status">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <select name="type" aria-label="Package type">
            <option value="">All types</option>
            @foreach (\App\Models\Package::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="roi" aria-label="ROI eligibility">
            <option value="">ROI any</option>
            <option value="yes" @selected(request('roi') === 'yes')>ROI eligible</option>
            <option value="no" @selected(request('roi') === 'no')>ROI not eligible</option>
        </select>
        <button>Search</button>
    </form>

    @if (($associatePreview['integrity_errors'] ?? []) === [])
        <x-ui.alert type="info">
            Associate Commission Structure {{ $associatePreview['version_code'] }} is stored and inactive.
            Posting: {{ $associatePreview['posting_enabled'] ? 'ENABLED (unsafe)' : 'disabled' }}.
            Snapshot on approval: {{ $associatePreview['snapshot_on_approval'] ? 'on' : 'disabled' }}.
            Theoretical totals are not earned or posted income.
            {{ $associatePreview['catalog_finding']['summary'] ?? '' }}
        </x-ui.alert>
    @else
        <x-ui.alert type="warning">
            @foreach ($associatePreview['integrity_errors'] as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Package price</th>
                    <th>Daily ROI</th>
                    <th>Legacy commission</th>
                    <th>Associate commission</th>
                    <th>Purchases</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                @php
                    $rule = $incomeRules[$package->code] ?? null;
                    $tier = $associateByCode[$package->code] ?? null;
                    $st = $statusById[$package->id] ?? null;
                @endphp
                <tr>
                    <td>
                        @if($package->image_path)
                            <img class="ds-package-thumb" src="{{ route('admin.packages.image', $package) }}" alt="">
                        @else
                            <span class="ds-package-media">No image</span>
                        @endif
                    </td>
                    <td>{{ $package->code }}</td>
                    <td>
                        <a href="{{ route('admin.packages.show', $package) }}">{{ $package->name }}</a>
                        @if ($st && ! ($st['posting_authorized'] ?? false) && ($st['catalog_active'] ?? false))
                            <div><small>Active · posting not authorized</small></div>
                        @endif
                    </td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td>
                        @if ($rule)
                            {{ $rule['roi_daily'] }} USDT / {{ $rule['roi_days'] }} days
                            <div><small>{{ $rule['source'] ?? '' }}</small></div>
                        @else
                            ROI Not Configured
                        @endif
                    </td>
                    <td>
                        @if ($rule && ($rule['commission_pool'] ?? null) !== null)
                            <div>
                                Plan/legacy pool {{ $rule['commission_pool'] }} USDT
                                @if (($rule['commission_days'] ?? null) !== null)
                                    / {{ $rule['commission_days'] }} days
                                @endif
                            </div>
                            @if (($rule['commission_days'] ?? null) !== null)
                                <div>Max {{ bcmul((string) $rule['commission_pool'], (string) $rule['commission_days'], 4) }} USDT (theoretical)</div>
                            @endif
                        @else
                            Plan/legacy commission Not configured
                        @endif
                    </td>
                    <td>
                        @if ($tier)
                            <div>{{ $tier['label'] }}</div>
                            <div>Version {{ $associatePreview['version_code'] }}</div>
                            <div>Associate pool {{ $tier['daily_total'] }} USDT / {{ $tier['commission_days'] }} days</div>
                            <div>Max {{ $tier['duration_total'] }} USDT (theoretical)</div>
                            <div>Snapshot: {{ $associatePreview['snapshot_on_approval'] ? 'flag on' : 'disabled' }}</div>
                            <div>Posting: {{ $associatePreview['posting_enabled'] ? 'ENABLED' : 'disabled' }}</div>
                        @else
                            Associate commission Not configured
                        @endif
                    </td>
                    <td>{{ $package->purchases_count }}</td>
                    <td>
                        {{ $package->is_active ? 'Active' : 'Inactive' }}
                        @if (! ($package->purchase_available ?? true))
                            <div><small>Purchase off</small></div>
                        @endif
                    </td>
                    <td>
                        <div>
                            <a href="{{ route('admin.packages.show', $package) }}">Details</a>
                            ·
                            <a href="{{ route('admin.packages.edit', $package) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.packages.status', $package) }}" onsubmit="return confirm('Confirm catalog status change? Income posting is not authorized by this action.');">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $package->is_active ? 0 : 1 }}">
                                <button type="submit">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11">No packages yet. Add a package and set its USDT price.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
