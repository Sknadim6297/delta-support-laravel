@extends('admin.layouts.app')
@section('title', 'ROI Schedule')
@section('page-title', 'ROI Schedule')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="ROI Management"
        title="ROI Schedule"
        lead="Automatic ROI generation is disabled. This page does not register a task or create a transaction."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.finance.roi-history') }}" variant="secondary">Open ROI History</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="ds-stat-grid">
        <x-ui.stat label="Scheduled tasks" :value="$scheduledTasks" hint="Tasks registered for this page" />
        <x-ui.stat label="Stored ROI records" :value="$roiCount" hint="Existing records only" />
        <x-ui.stat label="Legacy financial actions" :value="$legacyEnabled ? 'Enabled' : 'Disabled'" hint="Does not credit a wallet" />
        <x-ui.stat label="Accelerated testing" :value="$accelerated ? 'Enabled' : 'Disabled'" hint="Schedule testing flag" />
    </div>
</div>
@endsection
