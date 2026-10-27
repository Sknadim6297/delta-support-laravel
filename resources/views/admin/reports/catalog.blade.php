@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border bg-white p-6">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">{{ ucfirst($report) }} Reports</h1>
    @if($report === 'earnings')
        <p class="text-sm text-slate-600">Earnings are not calculated in this phase. ROI, level income, and payouts stay disabled.</p>
    @else
        <dl class="grid gap-2 text-sm">
            <div><dt class="font-semibold">Members</dt><dd>{{ $memberCount }}</dd></div>
            <div><dt class="font-semibold">Packages</dt><dd>{{ $packageCount }}</dd></div>
            <div><dt class="font-semibold">Pending payments</dt><dd>{{ $pendingPayments }}</dd></div>
            <div><dt class="font-semibold">Approved payments</dt><dd>{{ $approvedPayments }}</dd></div>
        </dl>
    @endif
</div>
@endsection
