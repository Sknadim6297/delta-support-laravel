@extends('admin.layouts.app')

@section('title', 'Security')
@section('page-title', 'Security')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="Security"
        lead="Change the administrator password. Login attempts stay rate limited."
    />

    <x-ui.card title="Administrator password" :subtitle="auth()->user()->email">
        <p class="ds-security-note">This password signs in to the admin panel. Login attempts stay rate limited.</p>
        <div class="ds-form-actions">
            <a class="ds-btn" href="{{ route('admin.change-password.index') }}">Change password</a>
        </div>
    </x-ui.card>
</div>
@endsection
