@extends('admin.layouts.app')
@section('title', ($mode ?? 'edit') === 'create' ? 'Add Package' : 'Edit Package')
@section('page-title', ($mode ?? 'edit') === 'create' ? 'Add Package' : 'Edit Package')

@section('content')
@php
    $isCreate = ($mode ?? 'edit') === 'create';
    $codeLocked = $codeLocked ?? false;
    $tourOnly = old('package_type', $package->package_type) === \App\Models\Package::TYPE_TOUR;
    $roiDaily = old('roi_daily', $plan->roi_daily ?? $displayRoi['daily'] ?? '');
    $roiDays = old('roi_days', $plan->roi_days ?? $displayRoi['days'] ?? '');
    $autoRoi = $autoRoiStatus ?? null;
@endphp
<div class="ds-catalog ds-package-admin">
    <x-ui.page-header
        :title="$isCreate ? 'Add Package' : 'Edit Package'"
        lead="Set package price, tour details and Daily ROI. Saving never credits wallets. Auto ROI is a separate ON/OFF control."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.packages.index')">All packages</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (! $isCreate && $autoRoi)
        <x-ui.alert type="{{ in_array($autoRoi['code'], ['on_scheduled', 'on_blocked_global'], true) ? 'success' : (($autoRoi['code'] === 'off' || $autoRoi['code'] === 'disabled') ? 'info' : 'warning') }}">
            Auto ROI Generation: {{ $autoRoi['label'] }}
            @if ($autoRoi['message'])
                — {{ $autoRoi['message'] }}
            @endif
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

        <x-ui.card title="Package" subtitle="Identification and price (USDT)">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Package code</span>
                    <input name="code" value="{{ old('code', $package->code) }}" required maxlength="32" @disabled($codeLocked) @readonly($codeLocked) placeholder="DST-TOUR-001">
                    @if ($codeLocked)
                        <input type="hidden" name="code" value="{{ $package->code }}">
                    @endif
                </label>
                <label class="ds-pkg-field">
                    <span>Package name</span>
                    <input name="name" value="{{ old('name', $package->name) }}" required maxlength="120" placeholder="SILVER PACKAGE">
                </label>
                <label class="ds-pkg-field">
                    <span>Package type</span>
                    <select name="package_type" id="package_type">
                        <option value="{{ \App\Models\Package::TYPE_TOUR_ROI }}" @selected(old('package_type', $package->package_type ?: \App\Models\Package::TYPE_TOUR_ROI) === \App\Models\Package::TYPE_TOUR_ROI)>ROI + Tour</option>
                        <option value="{{ \App\Models\Package::TYPE_PREMIUM }}" @selected(old('package_type', $package->package_type) === \App\Models\Package::TYPE_PREMIUM)>Premium Tour + ROI</option>
                        <option value="{{ \App\Models\Package::TYPE_TOUR }}" @selected(old('package_type', $package->package_type) === \App\Models\Package::TYPE_TOUR)>Tour Only (no ROI)</option>
                    </select>
                </label>
                <label class="ds-pkg-field">
                    <span>Package price (USDT)</span>
                    <input name="price_usd" value="{{ old('price_usd', $package->price_usd) }}" required inputmode="decimal" autocomplete="off" placeholder="50.0000">
                </label>
                <label class="ds-pkg-field">
                    <span>Catalog status</span>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $package->is_active ? '1' : '0') === '1' || old('is_active', $package->is_active) === true || old('is_active', $package->is_active) === 1)>Active</option>
                        <option value="0" @selected(old('is_active', $package->is_active ? '1' : '0') === '0' || old('is_active', $package->is_active) === false || old('is_active', $package->is_active) === 0)>Inactive</option>
                    </select>
                </label>
                <label class="ds-pkg-field">
                    <span>Display order</span>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required min="0">
                </label>
                <div class="ds-pkg-field ds-pkg-span-2 ds-pkg-image">
                    <span>Package image</span>
                    @if (! $isCreate && $package->image_path)
                        <img class="ds-package-thumb" src="{{ route('admin.packages.image', $package) }}" alt="">
                        <label class="ds-pkg-check"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
                    @endif
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Night" subtitle="Tour nights, days and food coupon">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Nights</span>
                    <input type="number" name="tour_nights" value="{{ old('tour_nights', $plan->tour_nights) }}" min="0" placeholder="1">
                </label>
                <label class="ds-pkg-field">
                    <span>Days</span>
                    <input type="number" name="tour_days" value="{{ old('tour_days', $plan->tour_days) }}" min="0" placeholder="2">
                </label>
                <label class="ds-pkg-field">
                    <span>Food coupon amount</span>
                    <input name="food_coupon_amount" value="{{ old('food_coupon_amount', $plan->food_coupon_amount) }}" inputmode="decimal" placeholder="Optional">
                </label>
                <label class="ds-pkg-field">
                    <span>Food coupon currency</span>
                    <input name="food_coupon_currency" value="{{ old('food_coupon_currency', $plan->food_coupon_currency ?? 'INR') }}" maxlength="16">
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Details" subtitle="Adult and child capacity">
            <div class="ds-pkg-grid">
                <label class="ds-pkg-field">
                    <span>Adults</span>
                    <input type="number" name="tour_adults" value="{{ old('tour_adults', $plan->tour_adults) }}" min="0" placeholder="2">
                </label>
                <label class="ds-pkg-field">
                    <span>Children</span>
                    <input type="number" name="tour_children" value="{{ old('tour_children', $plan->tour_children) }}" min="0" placeholder="1">
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Returns" subtitle="Daily ROI in USDT. Monthly and total returns calculate automatically.">
            @if ($tourOnly || ($package->package_type === \App\Models\Package::TYPE_TOUR && ! old('package_type')))
                <x-ui.alert type="info">Tour Only packages have no Daily ROI. Auto ROI stays OFF.</x-ui.alert>
            @endif
            <div class="ds-pkg-grid" id="roi-fields">
                <label class="ds-pkg-field">
                    <span>DAY — ROI duration (days)</span>
                    <input type="number" name="roi_days" id="roi_days" value="{{ $roiDays }}" min="1" placeholder="Enter duration days">
                </label>
                <label class="ds-pkg-field">
                    <span>TOTAL MONTH — months (30-day convention)</span>
                    <input id="total_months" value="" readonly placeholder="Auto calculated">
                </label>
                <label class="ds-pkg-field">
                    <span>PER DAY RETURN (USDT)</span>
                    <input name="roi_daily" id="roi_daily" value="{{ $roiDaily }}" inputmode="decimal" autocomplete="off" placeholder="Enter daily ROI">
                </label>
                <label class="ds-pkg-field">
                    <span>MONTHLY RETURN (USDT)</span>
                    <input id="monthly_return" value="" readonly placeholder="Daily × 30">
                </label>
                <label class="ds-pkg-field">
                    <span>ALL TOTAL RETURN (USDT)</span>
                    <input id="total_return" value="" readonly placeholder="Daily × days">
                </label>
            </div>
            <p class="ds-pkg-hint">Changing ROI creates a new plan version for future purchases only. Approved purchase snapshots stay unchanged.</p>
        </x-ui.card>

        <div class="ds-pkg-actions">
            <button type="submit" class="ds-btn">{{ $isCreate ? 'Create package' : 'Save package' }}</button>
        </div>
    </form>

    @if (! $isCreate)
        <x-ui.card title="Auto ROI Generation" subtitle="Package-wise control. Does not credit wallets when toggled.">
            @if ($autoRoi && ($autoRoi['locked'] ?? false))
                <p class="ds-pkg-hint">Auto ROI is disabled for this package type.</p>
            @else
                <form method="POST" action="{{ route('admin.packages.auto-roi', $package) }}" id="auto-roi-form" class="ds-settings-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="auto_roi_enabled" id="auto_roi_enabled_value" value="{{ ($package->auto_roi_enabled ?? false) ? '0' : '1' }}">
                    <input type="hidden" name="confirm_auto_roi" id="confirm_auto_roi" value="0">
                    <div class="ds-pkg-actions">
                        <button type="submit" class="ds-btn" id="auto-roi-toggle-btn">
                            Turn Auto ROI {{ ($package->auto_roi_enabled ?? false) ? 'OFF' : 'ON' }}
                        </button>
                    </div>
                    <p class="ds-pkg-hint">
                        Current: {{ $autoRoi['label'] ?? 'OFF' }}.
                        ON authorizes eligible scheduled accruals on the next authorized run. OFF stops new package ROI accruals without deleting history.
                        Global safety controls always win.
                    </p>
                </form>
            @endif
        </x-ui.card>
    @endif
</div>

<script>
(function () {
    const daily = document.getElementById('roi_daily');
    const days = document.getElementById('roi_days');
    const monthly = document.getElementById('monthly_return');
    const total = document.getElementById('total_return');
    const months = document.getElementById('total_months');
    const type = document.getElementById('package_type');
    const roiFields = document.getElementById('roi-fields');

    function mul4(a, b) {
        const aParts = String(a || '0').split('.');
        const bInt = BigInt(String(b || '0').split('.')[0] || '0');
        const aInt = BigInt(aParts[0] || '0') * 10000n + BigInt((aParts[1] || '').padEnd(4, '0').slice(0, 4) || '0');
        const product = aInt * bInt;
        const whole = product / 10000n;
        const frac = (product % 10000n).toString().padStart(4, '0');
        return whole.toString() + '.' + frac;
    }

    function refresh() {
        const tourOnly = type && type.value === 'tour';
        if (roiFields) {
            roiFields.style.opacity = tourOnly ? '0.45' : '1';
        }
        // Use readOnly (not disabled) so values still POST; server rejects ROI for Tour Only.
        if (daily) daily.readOnly = !!tourOnly;
        if (days) days.readOnly = !!tourOnly;
        if (tourOnly) {
            if (monthly) monthly.value = 'N/A';
            if (total) total.value = 'N/A';
            if (months) months.value = 'N/A';
            return;
        }
        if (!daily || !days || !daily.value || !days.value) {
            if (monthly) monthly.value = '';
            if (total) total.value = '';
            if (months) months.value = '';
            return;
        }
        try {
            monthly.value = mul4(daily.value, 30) + ' USDT';
            total.value = mul4(daily.value, days.value) + ' USDT';
            const d = parseInt(days.value, 10);
            if (!isNaN(d) && d > 0) {
                const whole = Math.floor(d / 30);
                const rem = d % 30;
                months.value = rem === 0 ? String(whole) : (d / 30).toFixed(2).replace(/\.?0+$/, '');
            }
        } catch (e) {
            monthly.value = '';
            total.value = '';
            months.value = '';
        }
    }

    ['input', 'change'].forEach(function (evt) {
        daily && daily.addEventListener(evt, refresh);
        days && days.addEventListener(evt, refresh);
        type && type.addEventListener(evt, refresh);
    });
    refresh();

    const autoForm = document.getElementById('auto-roi-form');
    if (autoForm) {
        autoForm.addEventListener('submit', function (event) {
            const turningOn = document.getElementById('auto_roi_enabled_value').value === '1';
            if (turningOn) {
                const ok = window.confirm(
                    'Turn Auto ROI Generation ON for this package?\n\n'
                    + 'This does not credit wallets now.\n'
                    + 'Eligible accruals begin on the next authorized scheduled run.\n'
                    + 'Global financial safety controls still apply.'
                );
                if (!ok) {
                    event.preventDefault();
                    return;
                }
                document.getElementById('confirm_auto_roi').value = '1';
            }
        });
    }
})();
</script>
@endsection
