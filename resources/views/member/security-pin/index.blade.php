@extends('member.layouts.app')
@section('title', 'Security PIN')
@section('page-title', 'Security PIN')

@section('content')
<div class="ds-pin-page">
    <div class="ds-pin-layout">
        <section class="ds-pin-aside" aria-labelledby="security-pin-heading">
            <p class="ds-pin-kicker">Account security</p>
            <h2 id="security-pin-heading">Security PIN</h2>
            <p class="ds-pin-copy">A 6-digit PIN protects withdrawals and wallet transfers. The saved PIN is never shown again. Password sign-in does not replace this PIN.</p>

            @if ($errors->any())
                <x-ui.alert type="error" class="ds-pin-banner" role="alert">{{ $errors->first() }}</x-ui.alert>
            @endif

            @if ($mailUnavailable)
                <x-ui.alert type="warning" class="ds-pin-banner">{{ $mailUnavailable }}</x-ui.alert>
            @endif

            @if ($transferReturn)
                <div class="ds-pin-banner is-transfer" role="alert">
                    <i class="ri-error-warning-line" aria-hidden="true"></i>
                    <p>A security PIN is required before Working Wallet funds can be transferred. After this PIN is saved, you return to Transfer Funds.</p>
                </div>
            @endif

            <div class="ds-pin-status">
                <article class="ds-pin-card {{ $pinSet ? 'is-ok' : 'is-warn' }}">
                    <span class="ds-pin-card-icon" aria-hidden="true"><i class="ri-shield-keyhole-line"></i></span>
                    <div>
                        <span class="ds-pin-card-label">Security PIN status</span>
                        <strong>{{ $pinSet ? 'Set' : 'Not set' }}</strong>
                        <small>{{ $pinSet && $member->security_pin_set_at ? 'Saved '.$member->security_pin_set_at->timezone('Asia/Kolkata')->format('d M Y, H:i') : 'Required before a withdrawal' }}</small>
                    </div>
                </article>
                <article class="ds-pin-card">
                    <span class="ds-pin-card-icon" aria-hidden="true"><i class="ri-mail-line"></i></span>
                    <div>
                        <span class="ds-pin-card-label">Reset email</span>
                        <strong>{{ $maskedEmail ?: 'Not available' }}</strong>
                        <small>Registered address, masked</small>
                    </div>
                </article>
                <article class="ds-pin-card {{ $locked ? 'is-danger' : 'is-ok' }}">
                    <span class="ds-pin-card-icon" aria-hidden="true"><i class="{{ $locked ? 'ri-lock-line' : 'ri-shield-check-line' }}"></i></span>
                    <div>
                        <span class="ds-pin-card-label">Verification / lock status</span>
                        <strong>{{ $locked ? 'Temporarily locked' : 'Available' }}</strong>
                        <small>{{ $locked ? 'Try again when the lock expires.' : 'Five incorrect attempts lock verification.' }}</small>
                    </div>
                </article>
            </div>

            <aside class="ds-pin-help">
                <h3>Before you choose a PIN</h3>
                <ul>
                    <li>Use six digits that are not all the same and not a straight sequence.</li>
                    <li>A reset code is sent only to the registered email and expires in 5 minutes.</li>
                    <li>The code is not shown on this page.</li>
                </ul>
            </aside>
        </section>

        <section class="ds-pin-main" aria-label="PIN actions">
            @if ($resetReady)
                <form method="POST" action="{{ route('member.security-pin.reset') }}" class="ds-pin-form">
                    @csrf
                    <header class="ds-pin-form-head">
                        <p>Verified reset</p>
                        <h2>Choose a new PIN</h2>
                        <span>The email code was verified. Enter the new PIN once.</span>
                    </header>
                    <div class="ds-pin-fields">
                        <x-security.pin-input name="pin" label="New security PIN" size="large" :autofocus="true" />
                        <x-security.pin-input name="pin_confirmation" label="Confirm new security PIN" size="large" />
                    </div>
                    <p class="ds-pin-hint">Repeated digits and ascending or descending sequences are rejected.</p>
                    <x-ui.button type="submit">Save new PIN</x-ui.button>
                </form>
            @elseif (! $pinSet)
                <form method="POST" action="{{ route('member.security-pin.store') }}" class="ds-pin-form">
                    @csrf
                    <header class="ds-pin-form-head">
                        <p>First-time setup</p>
                        <h2>Set PIN</h2>
                        <span>Choose a private 6-digit combination that only you know.</span>
                    </header>
                    <div class="ds-pin-fields">
                        <x-security.pin-input name="pin" label="Security PIN" size="large" :autofocus="true" />
                        <x-security.pin-input name="pin_confirmation" label="Confirm security PIN" size="large" />
                    </div>
                    <p class="ds-pin-hint">Repeated digits and ascending or descending sequences are rejected.</p>
                    <x-ui.button type="submit">Set PIN</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('member.security-pin.change') }}" class="ds-pin-form">
                    @csrf
                    <header class="ds-pin-form-head">
                        <p>Update</p>
                        <h2>Change PIN</h2>
                        <span>Enter the current PIN, then choose a new one.</span>
                    </header>
                    <div class="ds-pin-fields">
                        <x-security.pin-input name="current_pin" label="Current security PIN" size="large" :autofocus="true" />
                        <x-security.pin-input name="pin" label="New security PIN" size="large" />
                        <x-security.pin-input name="pin_confirmation" label="Confirm new security PIN" size="large" />
                    </div>
                    <p class="ds-pin-hint">Repeated digits and ascending or descending sequences are rejected.</p>
                    <x-ui.button type="submit">Change PIN</x-ui.button>
                </form>

                @if ($otpPending || $errors->has('otp'))
                    <form method="POST" action="{{ route('member.security-pin.verify') }}" class="ds-pin-form">
                        @csrf
                        <header class="ds-pin-form-head">
                            <p>Email verification</p>
                            <h2>Enter reset code</h2>
                            <span>Enter the 6-digit code from the email. It expires in 5 minutes.</span>
                        </header>
                        <div class="ds-pin-fields">
                            <x-security.pin-input name="otp" label="Email code" size="large" />
                        </div>
                        <x-ui.button type="submit">Verify code</x-ui.button>
                    </form>
                @endif

                <form method="POST" action="{{ route('member.security-pin.forgot') }}" class="ds-pin-form is-quiet">
                    @csrf
                    <header class="ds-pin-form-head">
                        <p>Recovery</p>
                        <h2>Forgot PIN</h2>
                        <span>A reset code is sent only to the registered email. The code is not shown on this page.</span>
                    </header>
                    <x-ui.button type="submit" variant="secondary">Send reset code</x-ui.button>
                </form>
            @endif
        </section>
    </div>
</div>

<style>
    .wrapper .ds-content .ds-pin-page {
        width: 100%;
    }

    .wrapper .ds-content .ds-pin-layout {
        display: grid;
        grid-template-columns: minmax(280px, 0.92fr) minmax(340px, 1.08fr);
        gap: 24px;
        align-items: start;
    }

    .wrapper .ds-content .ds-pin-page section {
        border: 0;
        border-radius: 0;
        background: transparent;
        padding: 0;
        box-shadow: none;
    }

    .wrapper .ds-content .ds-pin-aside,
    .wrapper .ds-content .ds-pin-main {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 16px;
    }

    .wrapper .ds-content .ds-pin-kicker {
        margin: 0;
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .wrapper .ds-content .ds-pin-aside h2 {
        margin: 0;
        color: #092747;
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -0.04em;
        line-height: 1.1;
    }

    .wrapper .ds-content .ds-pin-copy {
        margin: 0;
        max-width: 46ch;
        color: #526277;
        font-size: 15px;
        line-height: 1.55;
    }

    .wrapper .ds-content .ds-pin-banner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin: 0;
        border: 1px solid #f3d48a;
        border-left: 4px solid #d9890b;
        border-radius: 14px;
        background: linear-gradient(180deg, #fff8eb 0%, #fff 70%);
        padding: 14px 16px;
        box-shadow: 0 8px 20px rgba(217, 137, 11, 0.08);
        color: #8a5a08;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.45;
    }

    .wrapper .ds-content .ds-pin-banner.is-transfer i {
        flex: none;
        margin-top: 1px;
        font-size: 20px;
    }

    .wrapper .ds-content .ds-pin-banner p {
        margin: 0;
        color: #6b4708;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.45;
    }

    .wrapper .ds-content .ds-pin-banner.ds-alert-error {
        border-color: #fecaca;
        border-left-color: #dc2626;
        background: linear-gradient(180deg, #fff5f5 0%, #fff 72%);
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.08);
        color: #b91c1c;
    }

    .wrapper .ds-content .ds-pin-status {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .wrapper .ds-content .ds-pin-card,
    .wrapper .ds-content .ds-pin-help {
        border: 1px solid #e3ebf5;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(9, 39, 71, 0.05);
    }

    .wrapper .ds-content .ds-pin-card {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 16px 15px;
    }

    .wrapper .ds-content .ds-pin-card-icon {
        display: inline-flex;
        width: 42px;
        height: 42px;
        flex: none;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #eef5ff;
        color: #2563eb;
        font-size: 20px;
    }

    .wrapper .ds-content .ds-pin-card.is-ok .ds-pin-card-icon {
        background: #e8f8ef;
        color: #15803d;
    }

    .wrapper .ds-content .ds-pin-card.is-warn .ds-pin-card-icon {
        background: #fff6e6;
        color: #d9890b;
    }

    .wrapper .ds-content .ds-pin-card.is-danger .ds-pin-card-icon {
        background: #fef2f2;
        color: #dc2626;
    }

    .wrapper .ds-content .ds-pin-card-label,
    .wrapper .ds-content .ds-pin-form-head span {
        color: #667085;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.3;
    }

    .wrapper .ds-content .ds-pin-card strong {
        display: block;
        margin-top: 3px;
        color: #092747;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .wrapper .ds-content .ds-pin-card small {
        display: block;
        margin-top: 4px;
        color: #8795a8;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.4;
    }

    .wrapper .ds-content .ds-pin-help {
        padding: 16px 18px 14px;
    }

    .wrapper .ds-content .ds-pin-help h3 {
        margin: 0 0 8px;
        color: #092747;
        font-size: 14px;
        font-weight: 700;
    }

    .wrapper .ds-content .ds-pin-help ul {
        margin: 0;
        padding-left: 18px;
    }

    .wrapper .ds-content .ds-pin-help li {
        margin: 0;
        color: #526277;
        font-size: 13px;
        line-height: 1.5;
    }

    .wrapper .ds-content .ds-pin-help li + li {
        margin-top: 6px;
    }

    .wrapper .ds-content form.ds-pin-form {
        display: flex;
        width: 100%;
        max-width: none;
        flex-direction: column;
        flex-wrap: nowrap;
        align-items: stretch;
        gap: 18px;
        margin: 0;
        border: 1px solid #e3ebf5;
        border-radius: 18px;
        background: #fff;
        padding: 22px;
        box-shadow: 0 16px 36px rgba(9, 39, 71, 0.07);
    }

    .wrapper .ds-content form.ds-pin-form > * {
        width: 100%;
        max-width: none;
    }

    .wrapper .ds-content form.ds-pin-form.is-quiet {
        background: linear-gradient(180deg, #f8fbff 0%, #fff 48%);
    }

    .wrapper .ds-content .ds-pin-form-head p {
        margin: 0 0 4px;
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .wrapper .ds-content .ds-pin-form-head h2 {
        margin: 0 0 6px;
        color: #092747;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }

    .wrapper .ds-content .ds-pin-form-head span {
        display: block;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.45;
    }

    .wrapper .ds-content .ds-pin-fields {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .wrapper .ds-content .ds-pin-hint {
        margin: -4px 0 0;
        color: #667085;
        font-size: 13px;
        line-height: 1.45;
    }

    .wrapper .ds-content form.ds-pin-form button[type="submit"] {
        width: 100%;
        min-height: 48px;
        border-radius: 14px;
        font-size: 15px;
        font-weight: 700;
    }

    @media (max-width: 980px) {
        .wrapper .ds-content .ds-pin-layout {
            grid-template-columns: 1fr;
        }

        .wrapper .ds-content .ds-pin-aside h2 {
            font-size: 28px;
        }
    }

    @media (max-width: 640px) {
        .wrapper .ds-content form.ds-pin-form {
            padding: 16px;
            border-radius: 16px;
        }

        .wrapper .ds-content .ds-pin-card {
            padding: 14px;
        }
    }
</style>
@endsection
