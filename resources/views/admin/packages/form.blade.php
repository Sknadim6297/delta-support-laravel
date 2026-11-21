@extends('admin.layouts.app')
@section('title', $package->exists ? 'Edit Package' : 'Add Package')
@section('page-title', $package->exists ? 'Edit Package' : 'Add Package')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        :title="$package->exists ? 'Edit Package' : 'Add Package'"
        lead="New packages are priced in USDT. Saving an existing package keeps its stored denomination. Earlier purchases keep their own snapshot. Saving this form does not credit ROI or level commission."
    />
    <form class="ds-settings-form" method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" enctype="multipart/form-data">
        @csrf
        @if($package->exists) @method('PUT') @endif
        <div class="ds-field-grid">
            <label><span>Package code</span><input name="code" value="{{ old('code', $package->code) }}" required maxlength="32"></label>
            <label><span>Package name</span><input name="name" value="{{ old('name', $package->name) }}" required maxlength="120"></label>
            <label>
                <span>Package type</span>
                <select name="package_type">
                    @foreach(\App\Models\Package::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('package_type', $package->package_type ?: \App\Models\Package::TYPE_TOUR) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>{{ $package->exists ? 'Stored price ('.($package->denomination ?: 'USD').')' : 'Package price (USDT)' }}</span><input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal" autocomplete="off"></label>
            <label><span>Display order</span><input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required min="0"></label>
            <label><span>Duration (days / nights)</span><input name="duration" value="{{ old('duration', $package->duration) }}" maxlength="80" placeholder="Entered by admin"></label>
            <label><span>Short description</span><textarea name="description" rows="3" maxlength="500">{{ old('description', $package->description) }}</textarea></label>
            <label><span>Full description</span><textarea name="full_description" rows="5" maxlength="10000">{{ old('full_description', $package->full_description) }}</textarea></label>
            <label><span>Tour title</span><input name="tour_title" value="{{ old('tour_title', $package->tour_title) }}" maxlength="160"></label>
            <label><span>Tour destinations</span><textarea name="destinations" rows="3" maxlength="2000">{{ old('destinations', $package->destinations) }}</textarea></label>
            <label><span>Tour details</span><textarea name="tour_details" rows="4">{{ old('tour_details', $package->tour_details) }}</textarea></label>
            <label><span>Package benefits</span><textarea name="benefits" rows="4">{{ old('benefits', $package->benefits) }}</textarea></label>
            <label><span>Inclusions</span><textarea name="inclusions" rows="3">{{ old('inclusions', $package->inclusions) }}</textarea></label>
            <label><span>Exclusions</span><textarea name="exclusions" rows="3">{{ old('exclusions', $package->exclusions) }}</textarea></label>
            <label class="ds-field-span"><span>Package image</span>
                @if($package->exists && $package->image_path)
                    <img class="ds-package-thumb" src="{{ route('admin.packages.image', $package) }}" alt="Current package image">
                @else
                    <span class="ds-package-media">No package image yet</span>
                @endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </label>
            @if($package->exists && $package->image_path)
                <label class="ds-check"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
            @endif
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> Active</label>
            <p>Daily ROI and level-commission amounts come from the package income configuration. Saving this form does not credit income.</p>
            @if($errors->any())<div>{{ $errors->first() }}</div>@endif
        </div>

        @if ($package->exists)
            <x-ui.card title="Legacy package income (read-only)" subtitle="From package_income config only. Saving this form does not change wallets or ROI posting.">
                <p>
                    ROI:
                    @if ($incomeRule)
                        {{ $incomeRule['roi_daily'] }} USDT / {{ $incomeRule['roi_days'] }} days
                    @else
                        ROI Not Configured
                    @endif
                </p>
                <p>
                    Legacy commission:
                    @if ($incomeRule && isset($incomeRule['commission_pool'], $incomeRule['commission_days']))
                        pool {{ $incomeRule['commission_pool'] }} USDT / {{ $incomeRule['commission_days'] }} days
                        · max {{ bcmul((string) $incomeRule['commission_pool'], (string) $incomeRule['commission_days'], 4) }} USDT (theoretical)
                    @else
                        Legacy commission Not configured
                    @endif
                </p>
            </x-ui.card>

            <x-ui.card title="Associate Commission (read-only)" subtitle="From associate_v1 by package code. Separate from legacy commission_pool. Does not credit wallets.">
                @if ($associateTier)
                    <p>
                        Associate tier: {{ $associateTier['label'] }} · code {{ $associateTier['mapped_package_code'] }} ·
                        version {{ $associatePreview['version_code'] ?? 'associate_v1' }} ·
                        Associate pool {{ $associateTier['daily_total'] }} USDT / {{ $associateTier['commission_days'] }} days ·
                        max {{ $associateTier['duration_total'] }} USDT (theoretical) ·
                        snapshot {{ ($associatePreview['snapshot_on_approval'] ?? false) ? 'flag on' : 'disabled' }} ·
                        posting {{ ($associatePreview['posting_enabled'] ?? false) ? 'ENABLED' : 'disabled' }}
                    </p>
                    <div class="ds-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Daily USDT</th>
                                    <th>400-day USDT</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($associateTier['ranks'] as $rank)
                                <tr>
                                    <td>{{ $rank['rank'] }}</td>
                                    <td>{{ $rank['daily_amount'] }}</td>
                                    <td>{{ $rank['duration_amount'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p>Associate commission Not configured for this package code. Mapping uses package code only (not price).</p>
                @endif
            </x-ui.card>
        @endif

        <div>
            <button type="submit">Save package</button>
            <a href="{{ route('admin.packages.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
