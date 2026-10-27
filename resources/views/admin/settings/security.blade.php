@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Security</h1>
        <p class="ds-page-copy">Change the administrator password. Login attempts stay rate limited.</p>
    </header>
    <div class="ds-card">
        <a href="{{ route('admin.change-password.index') }}" class="inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Change password</a>
    </div>
</div>
@endsection
