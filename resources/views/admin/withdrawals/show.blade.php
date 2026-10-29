@extends('admin.layouts.app')
@section('title', $withdrawal->reference)
@section('page-title', 'Withdrawal Request')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        :title="$withdrawal->reference"
        :lead="$canReview ? 'Approval records the decision. It does not send cryptocurrency or a bank payment.' : 'Read-only request record. No review or payout action is available.'"
    >
        <x-slot:actions>
            @include('partials.withdrawal-status', ['status' => $withdrawal->status])
            <x-ui.button href="{{ route('admin.withdrawals.index') }}" variant="secondary">Back to requests</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    @if ($canReview)
        <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal) }}">
            @csrf
            <button type="submit">Approve request</button>
        </form>
        <form method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}">
            @csrf
            <label>
                <span>Rejection reason</span>
                <input name="reason" value="{{ old('reason') }}" maxlength="1000" required>
            </label>
            <button type="submit">Reject request</button>
        </form>
    @endif
</div>
@endsection
