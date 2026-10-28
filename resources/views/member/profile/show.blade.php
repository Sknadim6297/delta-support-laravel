@extends('member.layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
@php
    $accountActive = $member->status === 'active';
@endphp
<div class="ds-account-page">
    <x-ui.page-header
        title="My Profile"
        lead="Update your name and contact details. Member ID, sponsor, and account status stay on the registration record."
    >
        <x-slot:actions>
            <x-ui.button href="#edit-profile">Edit Profile</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Member overview" subtitle="Identifiers from your account. These cannot be edited here.">
        <div class="ds-profile-identity">
            <h2>{{ $member->member_name }}</h2>
            <x-ui.badge :tone="$accountActive ? 'success' : 'warning'">{{ $accountActive ? 'Active' : 'Inactive' }}</x-ui.badge>
            <span class="ds-profile-id">{{ $member->member_id }}</span>
        </div>
        <div class="ds-member-summary">
            <x-ui.stat label="Member ID" :value="$member->member_id" />
            <x-ui.stat label="Sponsor ID" :value="$member->sponsor_id ?: 'Not recorded'" />
            <x-ui.stat label="Sponsor Name" :value="$member->sponsor_name ?: 'Not recorded'" />
            <x-ui.stat label="Registered" :value="$member->created_at?->timezone('Asia/Kolkata')->format('d M Y') ?? 'N/A'" />
        </div>
        <dl class="ds-detail ds-profile-facts">
            <div><dt>Email Address</dt><dd>{{ $member->email }}</dd></div>
            <div><dt>Mobile Number</dt><dd>{{ $member->mobile_no }}</dd></div>
            <div><dt>PAN Card Number</dt><dd>{{ $member->pan_card_no ?: 'Not added' }}</dd></div>
            <div><dt>BEP20 Wallet Address</dt><dd>{{ $member->wallet_address ?: 'Not added' }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Profile information" subtitle="Email and mobile changes ask for your current password. PAN and the withdrawal wallet stay read-only.">
        @if ($errors->any())
            <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('member.profile.update') }}" class="ds-profile-form" id="edit-profile">
            @csrf
            @method('PUT')

            <div class="ds-field-grid">
                <div>
                    <label for="member_name">Member Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="member_name" name="member_name" value="{{ old('member_name', $member->member_name) }}" maxlength="100" autocomplete="name" required>
                    <div class="validation-message" data-error-for="member_name">@error('member_name'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="email">Email Address <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $member->email) }}" autocomplete="email" required>
                    <div class="validation-message" data-error-for="email">@error('email'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="mobile_no">Mobile Number <span aria-hidden="true">*</span></label>
                    <input type="text" id="mobile_no" name="mobile_no" value="{{ old('mobile_no', $member->mobile_no) }}" maxlength="15" inputmode="tel" autocomplete="tel" required>
                    <div class="validation-message" data-error-for="mobile_no">@error('mobile_no'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="pan_card_no">PAN Card Number</label>
                    <input type="text" id="pan_card_no" name="pan_card_no" value="{{ $member->pan_card_no }}" readonly aria-readonly="true">
                    <p class="ds-field-note">Read-only. Changing a PAN needs a verification step that is not available on this account yet.</p>
                    <div class="validation-message" data-error-for="pan_card_no">@error('pan_card_no'){{ $message }}@enderror</div>
                </div>
                <div class="ds-field-span-2">
                    <label for="wallet_address">BEP20 Wallet Address</label>
                    <input type="text" id="wallet_address" name="wallet_address" value="{{ $member->wallet_address }}" readonly aria-readonly="true">
                    <p class="ds-field-note">Read-only. A saved withdrawal wallet is not replaced from this page until wallet verification is available.</p>
                    <div class="validation-message" data-error-for="wallet_address">@error('wallet_address'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" placeholder="Required when changing email or mobile">
                    <p class="ds-field-note">Required only when the email address or mobile number changes.</p>
                    <div class="validation-message" data-error-for="current_password">@error('current_password'){{ $message }}@enderror</div>
                </div>
            </div>

            <div class="ds-form-actions">
                <button type="submit" class="ds-btn">Save Profile</button>
                <a class="ds-btn ds-btn-secondary" href="{{ route('member.profile') }}">Cancel</a>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
