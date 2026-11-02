@extends('admin.layouts.app')
@section('title', 'Purchase for Customer')
@section('page-title', 'Purchase for Customer')

@section('content')
@php
    $activeConflict = $errors->first('package_id') === 'This customer already has an active purchase of this package.';
@endphp
<div class="ds-catalog">
    <x-ui.page-header
        lead="Record a payment already received for an existing customer. An inactive customer is marked active. Wallet balances, ROI, level commission, and financial controls are not changed."
    />
    <form class="ds-settings-form ds-customer-purchase" method="POST" action="{{ route('admin.purchases.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <div class="ds-customer-grid">
            <section class="ds-customer-section">
                <h2>Customer</h2>
                <div class="ds-customer-lookup">
                    <label for="customer-member-id">
                        <span>Member ID</span>
                        <input id="customer-member-id" name="member_id" value="{{ old('member_id') }}" required maxlength="32" autocomplete="off" placeholder="ST100001">
                    </label>
                    <button type="button" class="ds-btn ds-btn-secondary" id="customer-lookup-button">Find Customer</button>
                </div>
                <p id="customer-lookup" class="ds-customer-note" role="status">@error('member_id'){{ $message }}@enderror</p>
                <div id="customer-panel" class="ds-info-panel" hidden>
                    <h3>Customer details</h3>
                    <dl>
                        <div><dt>Name</dt><dd id="customer-name">—</dd></div>
                        <div><dt>Member ID</dt><dd id="customer-id">—</dd></div>
                        <div><dt>Account status</dt><dd id="customer-status">—</dd></div>
                    </dl>
                </div>
            </section>

            <section class="ds-customer-section">
                <h2>Package</h2>
                <label for="package-id">
                    <span>Package selection</span>
                    <select id="package-id" name="package_id" required>
                        <option value="">Choose a package</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}"
                                data-price="{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}"
                                data-type="{{ $package->typeLabel() }}"
                                data-roi="{{ $package->roi_eligible ? 'Eligible' : 'Not eligible' }}"
                                data-level="{{ $package->level_commission_eligible ? 'Eligible' : 'Not eligible' }}"
                                @selected((string) old('package_id') === (string) $package->id)>
                                {{ $package->name }} — {{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div id="package-panel" class="ds-info-panel" hidden>
                    <h3>Package details</h3>
                    <dl>
                        <div><dt>Price</dt><dd id="package-price">—</dd></div>
                        <div><dt>Type</dt><dd id="package-type">—</dd></div>
                        <div><dt>ROI eligibility</dt><dd id="package-roi">—</dd></div>
                        <div><dt>Level commission</dt><dd id="package-level">—</dd></div>
                        <div><dt>Purchase status</dt><dd id="package-status">No purchase yet</dd></div>
                    </dl>
                    <p class="ds-customer-note">Eligibility is stored on the package. Recording this purchase does not credit ROI or level commission.</p>
                </div>
                <div id="package-warning" class="ds-alert ds-alert-warning" @unless($activeConflict) hidden @endunless>
                    <p>This customer already has an active purchase of this package.</p>
                    <dl>
                        <div><dt>Purchase reference</dt><dd id="active-reference">—</dd></div>
                        <div><dt>Approval date</dt><dd id="active-approved">—</dd></div>
                        <div><dt>Status</dt><dd id="active-status">Approved</dd></div>
                    </dl>
                </div>
                <div id="package-pending" class="ds-alert" hidden>
                    <p>This customer has a pending purchase of this package. It is not active.</p>
                    <dl>
                        <div><dt>Purchase reference</dt><dd id="pending-reference">—</dd></div>
                        <div><dt>Request date</dt><dd id="pending-requested">—</dd></div>
                        <div><dt>Status</dt><dd id="pending-status">Pending</dd></div>
                    </dl>
                </div>
            </section>
        </div>

        <div class="ds-customer-pay">
            <label for="payment-method">
                <span>Payment method</span>
                <select id="payment-method" name="payment_method" required>
                    @foreach(\App\Models\PackagePurchase::PAYMENT_METHODS as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', \App\Models\PackagePurchase::METHOD_CASH) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label for="payment-reference">
                <span id="payment-reference-label">Receipt number</span>
                <input id="payment-reference" name="payment_reference" value="{{ old('payment_reference') }}" required maxlength="255" autocomplete="off">
            </label>
        </div>

        @if($errors->any() && ! $activeConflict)
            <div class="ds-alert ds-alert-error">{{ $errors->first() }}</div>
        @endif

        <div class="ds-form-actions">
            <button type="submit" class="ds-btn" id="record-purchase" @disabled($activeConflict)>Record Purchase</button>
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.purchases.index') }}">Cancel</a>
        </div>
    </form>
</div>
<script>
    const lookupButton = document.getElementById('customer-lookup-button');
    const memberInput = document.getElementById('customer-member-id');
    const lookupResult = document.getElementById('customer-lookup');
    const packageInput = document.getElementById('package-id');
    const methodInput = document.getElementById('payment-method');
    const referenceLabel = document.getElementById('payment-reference-label');
    const recordButton = document.getElementById('record-purchase');
    const customerPanel = document.getElementById('customer-panel');
    const packagePanel = document.getElementById('package-panel');
    const warningPanel = document.getElementById('package-warning');
    const pendingPanel = document.getElementById('package-pending');
    const previewUrl = @json(route('admin.purchases.preview'));
    const referenceLabels = {
        cash: 'Receipt number',
        bank_transfer: 'Bank transfer reference',
        usdt: 'USDT transaction ID'
    };

    function selectedPackageOption() {
        return packageInput?.selectedOptions?.[0] || null;
    }

    function showPackageBasics() {
        const option = selectedPackageOption();
        if (!option || !option.value) {
            packagePanel.hidden = true;
            return;
        }
        packagePanel.hidden = false;
        document.getElementById('package-price').textContent = option.dataset.price || '—';
        document.getElementById('package-type').textContent = option.dataset.type || '—';
        document.getElementById('package-roi').textContent = option.dataset.roi || '—';
        document.getElementById('package-level').textContent = option.dataset.level || '—';
    }

    function setBlocked(blocked) {
        recordButton.disabled = blocked;
        warningPanel.hidden = !blocked;
        if (!blocked) {
            document.getElementById('active-reference').textContent = '—';
            document.getElementById('active-approved').textContent = '—';
            document.getElementById('active-status').textContent = 'Approved';
        }
    }

    function fillPurchase(prefix, purchase) {
        document.getElementById(prefix + '-reference').textContent = purchase?.reference || '—';
        if (prefix === 'active') {
            document.getElementById('active-approved').textContent = purchase?.approved_at || 'Not recorded';
            document.getElementById('active-status').textContent = [purchase?.status, purchase?.activation].filter(Boolean).join(' · ') || 'Approved';
        } else {
            document.getElementById('pending-requested').textContent = purchase?.submitted_at || 'Not recorded';
            document.getElementById('pending-status').textContent = purchase?.status || 'Pending';
        }
    }

    async function refreshPreview() {
        const memberId = (memberInput?.value || '').trim();
        showPackageBasics();
        pendingPanel.hidden = true;
        if (!memberId) {
            customerPanel.hidden = true;
            lookupResult.textContent = 'Enter a Member ID.';
            setBlocked(false);
            document.getElementById('package-status').textContent = 'No purchase yet';
            return;
        }

        lookupResult.textContent = 'Looking up the customer...';
        const params = new URLSearchParams({ member_id: memberId });
        if (packageInput?.value) {
            params.set('package_id', packageInput.value);
        }

        try {
            const response = await fetch(previewUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (!data.found) {
                customerPanel.hidden = true;
                lookupResult.textContent = data.message || 'No customer found for that Member ID.';
                setBlocked(false);
                document.getElementById('package-status').textContent = 'No purchase yet';
                return;
            }

            customerPanel.hidden = false;
            document.getElementById('customer-name').textContent = data.member.member_name;
            document.getElementById('customer-id').textContent = data.member.member_id;
            document.getElementById('customer-status').textContent = data.member.status_label;
            lookupResult.textContent = '';

            if (!data.package) {
                setBlocked(false);
                document.getElementById('package-status').textContent = packageInput?.value ? 'Choose an active package.' : 'No purchase yet';
                return;
            }

            document.getElementById('package-price').textContent = data.package.price;
            document.getElementById('package-type').textContent = data.package.type;
            document.getElementById('package-roi').textContent = data.package.roi_eligible;
            document.getElementById('package-level').textContent = data.package.level_eligible;

            if (data.blocked && data.active_purchase) {
                setBlocked(true);
                fillPurchase('active', data.active_purchase);
                pendingPanel.hidden = true;
                document.getElementById('package-status').textContent = data.active_purchase.status + ' · ' + data.active_purchase.activation;
                return;
            }

            setBlocked(false);
            if (data.pending_purchase) {
                pendingPanel.hidden = false;
                fillPurchase('pending', data.pending_purchase);
                document.getElementById('package-status').textContent = 'Pending · Not active';
                return;
            }

            document.getElementById('package-status').textContent = data.rejected_purchase
                ? 'Rejected · Not active'
                : 'No purchase yet';
        } catch (error) {
            lookupResult.textContent = 'The customer lookup did not respond.';
        }
    }

    methodInput?.addEventListener('change', function () {
        referenceLabel.textContent = referenceLabels[methodInput.value] || 'Receipt number';
    });
    if (methodInput && referenceLabel) {
        referenceLabel.textContent = referenceLabels[methodInput.value] || 'Receipt number';
    }

    lookupButton?.addEventListener('click', refreshPreview);
    packageInput?.addEventListener('change', refreshPreview);
    if ((memberInput?.value || '').trim() !== '') {
        refreshPreview();
    } else {
        showPackageBasics();
    }
</script>
@endsection
