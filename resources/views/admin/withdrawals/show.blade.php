@extends('admin.layouts.app')
@section('title', $withdrawal->reference)
@section('page-title', 'Withdrawal Request')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        :title="$withdrawal->reference"
        lead="Read-only request record. No review or payout action is available."
    >
        <x-slot:actions>
            @include('partials.withdrawal-status', ['status' => $withdrawal->status])
            <x-ui.button href="{{ route('admin.withdrawals.index') }}" variant="secondary">Back to requests</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
</div>
@endsection
