@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Payment Settings</h1>
        <p class="ds-page-copy">New payments use USDT. Choose the blockchain network explicitly. A submitted transaction ID is not proof of payment, and this application does not verify the blockchain. Approval stays manual. Historical USD purchases keep their saved denomination.</p>
    </header>
    <form method="POST" action="{{ route('admin.payments.settings.update') }}" enctype="multipart/form-data" class="ds-card">
        @csrf
        @method('PUT')
        <div class="ds-form ds-form-grid">
            <label class="ds-field"><span>Payment currency</span><input value="USDT" readonly></label>
            <label class="ds-field"><span>Accepted token</span><input name="currency_label" value="{{ old('currency_label', $settings->currency_label) }}" maxlength="40" placeholder="USDT" autocomplete="off"></label>
            <label class="ds-field"><span>Blockchain network</span><input name="network" value="{{ old('network', $settings->network) }}" maxlength="80" placeholder="Enter the network. None is assumed." autocomplete="off"></label>
            <label class="ds-field"><span>Receiving wallet address</span><input name="receiving_address" value="{{ old('receiving_address', $settings->receiving_address) }}" maxlength="255" autocomplete="off"></label>
            <label class="ds-field ds-span-2"><span>Payment instructions</span><textarea name="instructions" rows="4" maxlength="5000" placeholder="{{ \App\Models\PaymentSetting::USDT_INSTRUCTION }}">{{ old('instructions', $settings->instructions) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Transaction ID pattern</span><textarea name="reference_pattern" rows="2" maxlength="120" placeholder="Optional regular expression, for example ^[A-F0-9]{64}$">{{ old('reference_pattern', $settings->reference_pattern) }}</textarea></label>
            <label class="ds-field"><span>Payment QR image</span><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp" class="h-auto py-2"></label>
            <div class="ds-field justify-end">
                @if($settings->qr_image_path)
                    <img src="{{ route('admin.payments.qr') }}" alt="Configured payment QR" class="h-24 w-24 rounded border object-contain">
                @endif
            </div>
            <p class="ds-span-2 text-sm text-slate-600">Members must send USDT on the exact network entered here. Enter only the public receiving address and a JPG, PNG, or WebP QR. Do not store a private key or seed phrase.</p>
            <label class="ds-span-2 flex items-center gap-2 text-sm font-semibold text-navy-900">
                <input type="checkbox" name="is_enabled" value="1" class="h-4 w-4" @checked(old('is_enabled', $settings->is_enabled))>
                Enable payment submissions
            </label>
            @if($errors->any())<div class="ds-error ds-span-2">{{ $errors->first() }}</div>@endif
        </div>
        <div class="ds-actions">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
