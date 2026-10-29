@extends('member.layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
@php
    $accountActive = $member->status === 'active';
@endphp
<x-members.shell kicker="My account" lead="Update your name and contact details. Member ID, sponsor, and account status stay on the registration record.">
    <x-slot:actions>
        <x-ui.button href="#edit-profile">Edit profile</x-ui.button>
    </x-slot:actions>

    <div class="ds-mm-stats">
        <article class="ds-mm-stat">
            <span>Member</span>
            <strong>{{ $member->member_name }}</strong>
            <small>{{ $member->member_id }}</small>
        </article>
        <article class="ds-mm-stat">
            <span>Account</span>
            <strong>{{ $accountActive ? 'Active' : 'Inactive' }}</strong>
            <small>Saved status</small>
        </article>
        <article class="ds-mm-stat">
            <span>Sponsor</span>
            <strong>{{ $member->sponsor_id ?: 'Not recorded' }}</strong>
            <small>{{ $member->sponsor_name ?: 'Not recorded' }}</small>
        </article>
        <article class="ds-mm-stat">
            <span>Registered</span>
            <strong>{{ $member->created_at?->timezone('Asia/Kolkata')->format('d M Y') ?? 'N/A' }}</strong>
            <small>Account opened</small>
        </article>
    </div>

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('member.profile.update') }}" class="ds-profile-form ds-mm-form" id="edit-profile">
        @csrf
        @method('PUT')

        <x-members.panel title="Basic information" subtitle="These details can be saved from this page.">
            <div class="ds-mm-fields">
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
            </div>
        </x-members.panel>

        <x-members.panel title="KYC and wallet" subtitle="PAN and the withdrawal wallet stay read-only.">
            <div class="ds-mm-fields">
                <div>
                    <label for="pan_card_no">PAN Card Number</label>
                    <input type="text" id="pan_card_no" name="pan_card_no" value="{{ $member->pan_card_no }}" readonly aria-readonly="true">
                    <p class="ds-field-note">{{ $member->pan_card_no ?: 'Not added' }}. Read-only. Changing a PAN needs a verification step that is not available on this account yet.</p>
                    <div class="validation-message" data-error-for="pan_card_no">@error('pan_card_no'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="wallet_address">BEP20 Wallet Address</label>
                    <input type="text" id="wallet_address" name="wallet_address" value="{{ $member->wallet_address }}" readonly aria-readonly="true">
                    <p class="ds-field-note">{{ $member->wallet_address ?: 'Not added' }}. Read-only. A saved withdrawal wallet is not replaced from this page until wallet verification is available.</p>
                    <div class="validation-message" data-error-for="wallet_address">@error('wallet_address'){{ $message }}@enderror</div>
                </div>
            </div>
        </x-members.panel>

        <x-members.panel title="Security" subtitle="Required only when the email address or mobile number changes.">
            <div class="ds-mm-fields">
                <div>
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" placeholder="Required when changing email or mobile">
                    <div class="validation-message" data-error-for="current_password">@error('current_password'){{ $message }}@enderror</div>
                </div>
            </div>
            <div class="ds-mm-actions">
                <a class="ds-btn ds-btn-secondary" href="{{ route('member.profile') }}">Cancel</a>
                <button type="submit" class="ds-btn">Save Profile</button>
            </div>
        </x-members.panel>
    </form>
</x-members.shell>
@endsection
