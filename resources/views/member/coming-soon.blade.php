@extends('member.layouts.app')
@section('title', $feature . ' - Coming Soon')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">{{ $feature }}</h1>
        <p class="ds-page-copy">Coming Soon</p>
    </header>
    <div class="ds-card text-sm text-ink-500">This module is reserved for a later phase. No balances are credited from this page.</div>
</div>
@endsection