@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-4">
        <h1 class="ds-page-title">ROI Schedule</h1>
        <p class="ds-page-copy">Automatic ROI generation is disabled. This page does not register a task or create a transaction.</p>
    </header>
    <dl class="ds-card grid gap-3 sm:grid-cols-2">
        <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Scheduled tasks</dt><dd class="mt-1 font-semibold text-navy-900">{{ $scheduledTasks }}</dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Stored ROI records</dt><dd class="mt-1 font-semibold text-navy-900">{{ $roiCount }}</dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Legacy financial actions</dt><dd class="mt-1 font-semibold text-navy-900">{{ $legacyEnabled ? 'Enabled' : 'Disabled' }}</dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Accelerated testing</dt><dd class="mt-1 font-semibold text-navy-900">{{ $accelerated ? 'Enabled' : 'Disabled' }}</dd></div>
    </dl>
    <p class="mt-4 text-sm"><a class="font-semibold text-brand-700" href="{{ route('admin.finance.roi-history') }}">Open ROI History</a></p>
</div>
@endsection
