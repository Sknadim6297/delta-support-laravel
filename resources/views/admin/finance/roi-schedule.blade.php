@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>ROI Schedule</h1>
        <p>Automatic ROI generation is disabled. This page does not register a task or create a transaction.</p>
    </header>
    <dl>
        <div><dt>Scheduled tasks</dt><dd>{{ $scheduledTasks }}</dd></div>
        <div><dt>Stored ROI records</dt><dd>{{ $roiCount }}</dd></div>
        <div><dt>Legacy financial actions</dt><dd>{{ $legacyEnabled ? 'Enabled' : 'Disabled' }}</dd></div>
        <div><dt>Accelerated testing</dt><dd>{{ $accelerated ? 'Enabled' : 'Disabled' }}</dd></div>
    </dl>
    <p><a href="{{ route('admin.finance.roi-history') }}">Open ROI History</a></p>
</div>
@endsection
