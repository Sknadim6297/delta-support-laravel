@extends('admin.layouts.app')
@section('title', 'All Packages')
@section('page-title', 'All Packages')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header title="All Packages" lead="Tour details are stored on each package. Purchases keep their own price snapshot. Associate Commission ranks are preview-only until separately activated.">
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
                    <td>{{ $package->name }}</td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td>
                        @if ($rule)
                            {{ $rule['roi_daily'] }} USDT / {{ $rule['roi_days'] }} days
                        @else
                            ROI Not Configured
                        @endif
                    </td>
                    <td>
                        @if ($tier)
                            <div>{{ $tier['label'] }}</div>
                            <div>Version {{ $associatePreview['version_code'] }}</div>
                            <div>{{ $tier['daily_total'] }} USDT/day · 400 days</div>
                            <div>Max {{ $tier['duration_total'] }} USDT (theoretical)</div>
                            <div>Snapshot: {{ $associatePreview['snapshot_on_approval'] ? 'flag on' : 'disabled' }}</div>
                            <div>Posting: {{ $associatePreview['posting_enabled'] ? 'ENABLED' : 'disabled' }}</div>
                        @else
                            Not mapped to associate_v1 by code
                        @endif
                    </td>
                    <td>{{ $package->purchases_count }}</td>
                    <td>{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <div>
                            <a href="{{ route('admin.packages.edit', $package) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.packages.status', $package) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $package->is_active ? 0 : 1 }}">
                                <button type="submit">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">No packages yet. Add a package and set its USDT price.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>

    <x-ui.card title="Four-tier Associate Commission mapping (read-only)" subtitle="Identity is by package code. Price matches are hints only. Theoretical totals are not posted income.">
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tier</th>
                        <th>Mapped code</th>
                        <th>Catalog</th>
                        <th>Price</th>
                        <th>ROI</th>
                        <th>Daily pool</th>
                        <th>400-day max</th>
                        <th>Snapshot</th>
                        <th>Posting</th>
                        <th>Warnings</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($associatePreview['tiers'] as $tier)
                    <tr>
                        <td>{{ $tier['label'] }}</td>
                        <td>{{ $tier['mapped_package_code'] ?: '—' }}</td>
                        <td>
                            @if ($tier['catalog_package_found'])
                                Configured · {{ $tier['catalog_package_name'] }}
                            @else
                                Pending catalog row
                            @endif
                        </td>
                        <td>{{ $tier['package_price'] }} USDT</td>
                        <td>{{ $tier['roi_display'] }}</td>
                        <td>{{ $tier['daily_total'] }}</td>
                        <td>{{ $tier['duration_total'] }}</td>
                        <td>{{ $associatePreview['snapshot_on_approval'] ? 'Pending approval flag on' : 'Disabled' }}</td>
                        <td>{{ $associatePreview['posting_enabled'] ? 'Enabled' : 'Disabled' }}</td>
                        <td>
                            @forelse ($tier['warnings'] as $warning)
                                <div>{{ $warning }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p><a href="{{ route('admin.finance.commission-settings') }}">Open full Rank 1–20 breakdown</a></p>
    </x-ui.card>
</div>
@endsection
