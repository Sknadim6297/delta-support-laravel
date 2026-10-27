@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Branding</h1>
        <p class="ds-page-copy">The public site and both panels use these colors and the current logo.</p>
    </header>
    <div class="ds-card">
        <img src="{{ asset('site/img/logo.png') }}" alt="Delta Support" class="mb-4 h-16">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-navy-900">Primary</dt><dd class="mt-1 text-ink-500">#0878d1</dd></div>
            <div><dt class="font-semibold text-navy-900">Navy</dt><dd class="mt-1 text-ink-500">#092747</dd></div>
        </dl>
    </div>
</div>
@endsection
