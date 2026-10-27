@extends('admin.layouts.app')

@section('title', 'Payment Settings')
@section('page-title', 'Payment Settings')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Payments"
        title="Payment Settings"
        lead="New payments use USDT. Choose the blockchain network explicitly. A submitted transaction ID is not proof of payment, and this application does not verify the blockchain. Approval stays manual. Historical USD purchases keep their saved denomination."
    />

    <form class="ds-settings-form" method="POST" action="{{ route('admin.payments.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="ds-field-grid">
            <label><span>Payment currency</span><input value="USDT" readonly></label>
            <label><span>Accepted token</span><input name="currency_label" value="{{ old('currency_label', $settings->currency_label) }}" maxlength="40" placeholder="USDT" autocomplete="off"></label>
            <label><span>Blockchain network</span><input name="network" value="{{ old('network', $settings->network) }}" maxlength="80" placeholder="Enter the network. None is assumed." autocomplete="off"></label>
            <label><span>Receiving wallet address</span><input name="receiving_address" value="{{ old('receiving_address', $settings->receiving_address) }}" maxlength="255" autocomplete="off"></label>
            <label class="ds-field-span"><span>Payment instructions</span><textarea name="instructions" rows="4" maxlength="5000" placeholder="{{ \App\Models\PaymentSetting::USDT_INSTRUCTION }}">{{ old('instructions', $settings->instructions) }}</textarea></label>
            <label class="ds-field-span"><span>Transaction ID pattern</span><textarea name="reference_pattern" rows="2" maxlength="120" placeholder="Optional regular expression, for example ^[A-F0-9]{64}$">{{ old('reference_pattern', $settings->reference_pattern) }}</textarea></label>
            <label><span>Payment QR image</span><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp"></label>
        </div>

        <div class="ds-qr-preview">
            @if($settings->qr_image_path)
                <img src="{{ route('admin.payments.qr') }}" alt="Configured payment QR">
            @else
                <p>No payment QR uploaded yet.</p>
            @endif
        </div>

        <p>Members must send USDT on the exact network entered here. Enter only the public receiving address and a JPG, PNG, or WebP QR. Do not store a private key or seed phrase.</p>

        <label class="ds-check">
            <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $settings->is_enabled))>
            Enable payment submissions
        </label>

        @if($errors->any())
            <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <div class="ds-form-actions">
            <button type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
