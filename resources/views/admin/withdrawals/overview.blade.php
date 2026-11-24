@extends('admin.layouts.app')
@section('title', 'Withdrawal Overview')
@section('page-title', 'Withdrawal Overview')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        title="Withdrawal Overview"
        lead="Counts come from stored withdrawal requests. Review, holds, and payouts are not available."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.withdrawals.index') }}" variant="secondary">Withdrawal Requests</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-file-list-3-line" label="Total requests" :value="$total" hint="All stored requests" />
        @foreach ($counts as $status => $count)
            <a class="ds-stat-link" href="{{ route('admin.withdrawals.index', ['status' => $status]) }}">
                <x-ui.stat
                    icon="ri-arrow-right-up-line"
                    :tone="match ($status) {
                        'completed', 'approved' => 'green',
                        'pending_review', 'draft', 'processing' => 'amber',
                        'rejected', 'cancelled', 'failed' => 'orange',
                        default => 'teal',
                    }"
                    :label="\App\Support\WithdrawalStatus::label($status)"
                    :value="$count"
                    hint="Open this status"
                />
            </a>
        @endforeach
    </div>
</div>
@endsection
