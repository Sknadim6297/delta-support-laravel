@extends('admin.layouts.app')
@section('title', ($mode ?? 'edit') === 'create' ? 'Add Package' : 'Edit Package')
@section('page-title', ($mode ?? 'edit') === 'create' ? 'Add Package' : 'Edit Package')

@section('content')
@php
    $isCreate = ($mode ?? 'edit') === 'create';
    $codeLocked = $codeLocked ?? false;
    $hasRoiDefault = old('has_daily_roi', $plan->has_daily_roi ?? ($package->package_type !== \App\Models\Package::TYPE_TOUR));
@endphp
<div class="ds-catalog ds-package-admin">
    <x-ui.page-header
        :title="$isCreate ? 'Add Package' : 'Edit Package'"
        lead="Catalog Active is not financial posting. Saving never credits wallets. Approved purchases keep immutable snapshots."
    >
        <x-slot:actions>
            @if (! $isCreate)
                <x-ui.button variant="secondary" :href="route('admin.packages.show', $package)">Package details</x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('admin.packages.index')">All packages</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($status)
        <x-ui.alert type="{{ ($status['posting_authorized'] ?? false) ? 'warning' : 'info' }}">
            Catalog: {{ $status['catalog_active'] ? 'Active' : 'Inactive' }} ·
            Purchase: {{ $status['purchase_available'] ? 'Available' : 'Unavailable' }} ·
            ROI flag: {{ $status['roi_eligible'] ? 'Eligible' : 'Not eligible' }} ·
            Income source: {{ $status['income_source'] }} ·
            Accrual authority: {{ $status['accrual_authority'] ? 'yes' : 'no' }} ·
            {{ $status['posting_note'] }}
            Associate posting: {{ ($status['associate_posting'] ?? false) ? 'ENABLED' : 'disabled' }}.
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert type="danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    <form
        class="ds-settings-form ds-package-form"
        method="POST"
        action="{{ $isCreate ? route('admin.packages.store') : route('admin.packages.update', $package) }}"
        enctype="multipart/form-data"
        id="package-admin-form"
    >
        @csrf
        @if (! $isCreate) @method('PUT') @endif

        <x-ui.card title="General" subtitle="Package catalog fields">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Package code</span>
                    <input name="code" value="{{ old('code', $package->code) }}" required maxlength="32" @disabled($codeLocked) @readonly($codeLocked) placeholder="DST-TOUR-001">
                    @if ($codeLocked)
                        <input type="hidden" name="code" value="{{ $package->code }}">
                        <small class="ds-pkg-hint">Code locked after purchases exist.</small>
                    @endif
                </label>
                <label class="ds-pkg-field">
                    <span>Package name</span>
                    <input name="name" value="{{ old('name', $package->name) }}" required maxlength="120" placeholder="SILVER PACKAGE">
                </label>
                <label class="ds-pkg-field">
                    <span>Package type</span>
                    <select name="package_type" id="package_type">
                        @foreach(\App\Models\Package::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('package_type', $package->package_type ?: \App\Models\Package::TYPE_TOUR_ROI) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ds-pkg-field">
                    <span>Package price (USDT)</span>
                    <input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal" autocomplete="off" placeholder="50.0000">
                </label>
                <label class="ds-pkg-field">
                    <span>Display order</span>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required min="0">
                </label>
                <label class="ds-pkg-field">
                    <span>Duration label</span>
                    <input name="duration" value="{{ old('duration', $package->duration) }}" maxlength="80" placeholder="2 Days / 1 Night">
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Short description</span>
                    <textarea name="description" rows="3" maxlength="500" placeholder="Short package summary">{{ old('description', $package->description) }}</textarea>
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Full description</span>
                    <textarea name="full_description" rows="4" maxlength="10000" placeholder="Full package details">{{ old('full_description', $package->full_description) }}</textarea>
                </label>
                <div class="ds-pkg-field ds-pkg-span-2 ds-pkg-image">
                    <span>Package image</span>
                    <div class="ds-pkg-image-row">
                        @if($package->exists && $package->image_path)
                            <img class="ds-package-thumb" src="{{ route('admin.packages.image', $package) }}" alt="Current package image">
                        @endif
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    </div>
                    @if($package->exists && $package->image_path)
                        <label class="ds-pkg-check"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                    @endif
                </div>
                <div class="ds-pkg-checks ds-pkg-span-2">
                    <label class="ds-pkg-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> Catalog Active</label>
                    <label class="ds-pkg-check"><input type="checkbox" name="purchase_available" value="1" @checked(old('purchase_available', $package->purchase_available ?? true))> Purchase available</label>
                    <label class="ds-pkg-check"><input type="checkbox" name="roi_eligible" value="1" @checked(old('roi_eligible', $package->roi_eligible))> ROI eligibility flag</label>
                    <label class="ds-pkg-check"><input type="checkbox" name="level_commission_eligible" value="1" @checked(old('level_commission_eligible', $package->level_commission_eligible))> Level income eligibility</label>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Daily ROI" subtitle="Decimal-safe Total ROI. No final-day adjustment. Does not credit wallets.">
            <div class="ds-pkg-grid">
                <div class="ds-pkg-checks ds-pkg-span-2">
                    <label class="ds-pkg-check"><input type="checkbox" name="has_daily_roi" id="has_daily_roi" value="1" @checked(old('has_daily_roi', $hasRoiDefault))> Enable Daily ROI</label>
                </div>
                <label class="ds-pkg-field">
                    <span>Daily ROI (USDT)</span>
                    <input name="roi_daily" id="roi_daily" value="{{ old('roi_daily', $plan->roi_daily) }}" inputmode="decimal" autocomplete="off" placeholder="0.1058">
                </label>
                <label class="ds-pkg-field">
                    <span>ROI duration (days)</span>
                    <input type="number" name="roi_days" id="roi_days" value="{{ old('roi_days', $plan->roi_days) }}" min="1" placeholder="900">
                </label>
                <label class="ds-pkg-field">
                    <span>Total ROI (USDT)</span>
                    <input id="total_roi" value="" readonly placeholder="Auto calculated">
                </label>
                <label class="ds-pkg-field">
                    <span>Waiting period (days)</span>
                    <input type="number" name="wait_days" value="{{ old('wait_days', $plan->wait_days ?? 10) }}" min="0">
                </label>
                <label class="ds-pkg-field">
                    <span>ROI start rule</span>
                    <input name="roi_start_rule" value="{{ old('roi_start_rule', $plan->roi_start_rule ?? 'approval_plus_wait_days') }}" maxlength="64">
                </label>
                <label class="ds-pkg-field">
                    <span>ROI end rule</span>
                    <input name="roi_end_rule" value="{{ old('roi_end_rule', $plan->roi_end_rule ?? 'first_accrual_plus_duration_minus_one') }}" maxlength="64">
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Tour benefits" subtitle="Stored on the plan version for future purchases">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Tour title</span>
                    <input name="tour_title" value="{{ old('tour_title', $package->tour_title) }}" maxlength="160" placeholder="Digha holiday">
                </label>
                <label class="ds-pkg-field">
                    <span>Eligibility waiting days</span>
                    <input type="number" name="tour_eligible_after_days" value="{{ old('tour_eligible_after_days', $plan->tour_eligible_after_days) }}" min="0" placeholder="30">
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Tour destinations</span>
                    <textarea name="destinations" rows="2" maxlength="2000" placeholder="Digha, West Bengal">{{ old('destinations', $package->destinations) }}</textarea>
                </label>
                <label class="ds-pkg-field">
                    <span>Tour nights</span>
                    <input type="number" name="tour_nights" value="{{ old('tour_nights', $plan->tour_nights) }}" min="0" placeholder="1">
                </label>
                <label class="ds-pkg-field">
                    <span>Tour days</span>
                    <input type="number" name="tour_days" value="{{ old('tour_days', $plan->tour_days) }}" min="0" placeholder="2">
                </label>
                <label class="ds-pkg-field">
                    <span>Adult capacity</span>
                    <input type="number" name="tour_adults" value="{{ old('tour_adults', $plan->tour_adults) }}" min="0" placeholder="2">
                </label>
                <label class="ds-pkg-field">
                    <span>Child capacity</span>
                    <input type="number" name="tour_children" value="{{ old('tour_children', $plan->tour_children) }}" min="0" placeholder="1">
                </label>
                <label class="ds-pkg-field">
                    <span>Food coupon amount</span>
                    <input name="food_coupon_amount" value="{{ old('food_coupon_amount', $plan->food_coupon_amount) }}" inputmode="decimal" placeholder="1000.0000">
                </label>
                <label class="ds-pkg-field">
                    <span>Food coupon currency</span>
                    <input name="food_coupon_currency" value="{{ old('food_coupon_currency', $plan->food_coupon_currency ?? 'INR') }}" maxlength="16">
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Tour description</span>
                    <textarea name="tour_description" rows="3" placeholder="Tour benefit summary for this plan version">{{ old('tour_description', $plan->tour_description ?? $package->tour_details) }}</textarea>
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Tour details (catalog)</span>
                    <textarea name="tour_details" rows="3" placeholder="Catalog tour details">{{ old('tour_details', $package->tour_details) }}</textarea>
                </label>
                <label class="ds-pkg-field ds-pkg-span-2">
                    <span>Benefits</span>
                    <textarea name="benefits" rows="3" placeholder="Package benefits">{{ old('benefits', $package->benefits) }}</textarea>
                </label>
                <label class="ds-pkg-field">
                    <span>Inclusions</span>
                    <textarea name="inclusions" rows="3" placeholder="What is included">{{ old('inclusions', $package->inclusions) }}</textarea>
                </label>
                <label class="ds-pkg-field">
                    <span>Exclusions</span>
                    <textarea name="exclusions" rows="3" placeholder="What is excluded">{{ old('exclusions', $package->exclusions) }}</textarea>
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Commission (optional)" subtitle="Never auto-derived from ROI. Associate posting stays disabled.">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Legacy commission pool (USDT/day)</span>
                    <input name="commission_pool" value="{{ old('commission_pool', $plan->commission_pool) }}" inputmode="decimal" placeholder="Leave blank for ROI-only (not Associate pool)">
                </label>
                <label class="ds-pkg-field">
                    <span>Legacy commission days</span>
                    <input type="number" name="commission_days" value="{{ old('commission_days', $plan->commission_days) }}" min="1" placeholder="400">
                </label>
                <label class="ds-pkg-field">
                    <span>Change note</span>
                    <input name="change_note" value="{{ old('change_note', $plan->change_note) }}" maxlength="2000" placeholder="Why this version changed">
                </label>
                <label class="ds-pkg-field">
                    <span>Version label</span>
                    <input name="label" value="{{ old('label', $plan->label) }}" maxlength="120" placeholder="v1 plan label">
                </label>
            </div>
        </x-ui.card>

        @if (! $isCreate)
            <x-ui.card title="Legacy package income (read-only)" subtitle="From package_income config / resolver. Saving this form does not change wallets or ROI posting.">
                <div class="ds-pkg-readonly">
                    <p>
                        <strong>ROI:</strong>
                        @if ($incomeRule)
                            {{ $incomeRule['roi_daily'] }} USDT / {{ $incomeRule['roi_days'] }} days
                        @else
                            ROI Not Configured
                        @endif
                    </p>
                    <p>
                        <strong>Legacy commission:</strong>
                        @if ($incomeRule && ($incomeRule['commission_pool'] ?? null) !== null)
                            pool {{ $incomeRule['commission_pool'] }} USDT / {{ $incomeRule['commission_days'] ?? '—' }} days
                            @if (($incomeRule['commission_days'] ?? null) !== null)
                                · max {{ bcmul((string) $incomeRule['commission_pool'], (string) $incomeRule['commission_days'], 4) }} USDT (theoretical)
                            @endif
                        @else
                            Legacy commission Not configured
                        @endif
                    </p>
                </div>
            </x-ui.card>

            <x-ui.card title="Associate Commission (read-only)" subtitle="From associate_v1 by package code. Separate from legacy commission_pool. Does not credit wallets.">
                @if ($associateTier)
                    <div class="ds-pkg-readonly">
                        <p>
                            Associate tier: {{ $associateTier['label'] }} · code {{ $associateTier['mapped_package_code'] }} ·
                            version {{ $associatePreview['version_code'] ?? 'associate_v1' }} ·
                            Associate pool {{ $associateTier['daily_total'] }} USDT / {{ $associateTier['commission_days'] }} days ·
                            max {{ $associateTier['duration_total'] }} USDT (theoretical) ·
                            snapshot {{ ($associatePreview['snapshot_on_approval'] ?? false) ? 'flag on' : 'disabled' }} ·
                            posting {{ ($associatePreview['posting_enabled'] ?? false) ? 'ENABLED' : 'disabled' }}
                        </p>
                    </div>
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

            <x-ui.card title="Plan publish options" subtitle="Editing after approved purchases creates a new version for future purchases only.">
                <div class="ds-pkg-checks">
                    <label class="ds-pkg-check"><input type="checkbox" name="save_plan" value="1" checked> Save income/tour plan version with this update</label>
                    <label class="ds-pkg-check"><input type="checkbox" name="publish_plan" value="1" checked> Publish plan version for future purchases</label>
                </div>
                @if (($package->income_source ?? 'legacy_config') === 'legacy_config')
                    <x-ui.alert type="warning">
                        This package uses income_source=legacy_config. Published DB versions are stored for history/admin but do not replace legacy package_income accrual terms until a separate activation approval.
                    </x-ui.alert>
                @endif
            </x-ui.card>
        @endif

        <div class="ds-pkg-actions">
            <x-ui.button type="submit">{{ $isCreate ? 'Create package' : 'Save package' }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.packages.index')">Cancel</x-ui.button>
        </div>
    </form>

    @if (! $isCreate && ($versions ?? collect())->isNotEmpty())
        <x-ui.card title="Version history" subtitle="Immutable historical versions">
            <div class="ds-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ver</th>
                            <th>Status</th>
                            <th>Daily ROI</th>
                            <th>Days</th>
                            <th>Total</th>
                            <th>Accrual auth</th>
                            <th>Published</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($versions as $version)
                        <tr>
                            <td>v{{ $version->version_number }}</td>
                            <td>{{ $version->status }}</td>
                            <td>{{ $version->has_daily_roi ? $version->roi_daily : '—' }}</td>
                            <td>{{ $version->roi_days ?? '—' }}</td>
                            <td>{{ $version->totalRoi() ?? '—' }}</td>
                            <td>{{ $version->accrual_authority ? 'yes' : 'no' }}</td>
                            <td>{{ $version->published_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</div>

<script>
(function () {
    const daily = document.getElementById('roi_daily');
    const days = document.getElementById('roi_days');
    const total = document.getElementById('total_roi');
    const hasRoi = document.getElementById('has_daily_roi');
    const type = document.getElementById('package_type');

    function mul4(a, b) {
        const aParts = String(a || '0').split('.');
        const bParts = String(b || '0').split('.');
        const aInt = BigInt(aParts[0] || '0') * 10000n + BigInt((aParts[1] || '').padEnd(4, '0').slice(0, 4) || '0');
        const bInt = BigInt(bParts[0] || '0');
        const product = aInt * bInt;
        const whole = product / 10000n;
        const frac = (product % 10000n).toString().padStart(4, '0');
        return whole.toString() + '.' + frac;
    }

    function refresh() {
        const tourOnly = type && type.value === 'tour';
        if (tourOnly) {
            hasRoi.checked = false;
            hasRoi.disabled = true;
        } else {
            hasRoi.disabled = false;
        }
        const enabled = hasRoi.checked && !tourOnly;
        daily.disabled = !enabled;
        days.disabled = !enabled;
        if (!enabled || !daily.value || !days.value) {
            total.value = enabled ? '' : 'N/A (Tour Only)';
            return;
        }
        try {
            total.value = mul4(daily.value, days.value) + ' USDT';
        } catch (e) {
            total.value = '';
        }
    }

    ['input', 'change'].forEach(function (evt) {
        daily && daily.addEventListener(evt, refresh);
        days && days.addEventListener(evt, refresh);
        hasRoi && hasRoi.addEventListener(evt, refresh);
        type && type.addEventListener(evt, refresh);
    });
    refresh();
})();
</script>
@endsection
