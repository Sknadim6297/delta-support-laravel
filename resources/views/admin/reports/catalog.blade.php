@extends('admin.layouts.app')
@section('content')
<div>
    <h1>{{ ucfirst($report) }} Reports</h1>
    @if($report === 'earnings')
        <p>Earnings are not calculated in this phase. ROI, level income, and payouts stay disabled.</p>
        <dl>
            <div><dt>Stored ROI records</dt><dd>{{ $roiCount }}</dd></div>
            <div><dt>Stored level income records</dt><dd>{{ $levelCount }}</dd></div>
        </dl>
        <p>
            <a href="{{ route('admin.finance.roi-history') }}">ROI History</a>
            <a href="{{ route('admin.finance.level-income') }}">Level Income History</a>
        </p>
    @else
        <dl>
            <div><dt>Members</dt><dd>{{ $memberCount }}</dd></div>
            <div><dt>Packages</dt><dd>{{ $packageCount }}</dd></div>
            <div><dt>Pending payments</dt><dd>{{ $pendingPayments }}</dd></div>
            <div><dt>Approved payments</dt><dd>{{ $approvedPayments }}</dd></div>
        </dl>
    @endif
</div>
@endsection
