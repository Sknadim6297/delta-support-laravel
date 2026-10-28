@extends('admin.layouts.app')
@section('title', $qualification->member_id.' reward')
@section('page-title', $qualification->member_name)
@section('content')
<div class="ds-catalog">
    <x-ui.page-header :lead="$qualification->member_id.' · Sponsor '.$qualification->sponsor_id">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.eligible')" variant="secondary">Eligible members</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('success'))
        <x-ui.alert>{{ session('success') }}</x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <div class="ds-stat-grid">
        <x-ui.stat label="Campaign" :value="$qualification->campaign?->title" />
        <x-ui.stat label="Achieved" :value="\App\Support\MoneyDisplay::usdt($qualification->achieved_amount)" />
        <x-ui.stat label="Target" :value="\App\Support\MoneyDisplay::usdt($qualification->target_amount)" />
        <x-ui.stat label="Eligibility" :value="$qualification->statusLabel()" />
    </div>
    <x-ui.card title="Selection">
        <p>{{ $assignment?->gift_name ?: $qualification->campaign?->gift_name }}</p>
        <p>{{ $assignment?->statusLabel() ?? 'No gift has been selected for this row.' }}</p>
        @if ($assignment?->conflict_note)
            <x-ui.alert type="warning">{{ $assignment->conflict_note }}</x-ui.alert>
        @endif
        @if ($qualification->qualified_at)
            <p>Qualified {{ $qualification->qualified_at->timezone('Asia/Kolkata')->format('d M Y H:i') }} Asia/Kolkata</p>
        @endif
    </x-ui.card>
    @if ($delivery)
        <x-ui.card title="Delivery">
            <p><x-ui.badge :tone="$delivery->statusTone()">{{ $delivery->statusLabel() }}</x-ui.badge></p>
            @if ($delivery->method)
                <p>Method: {{ $delivery->method }}</p>
            @endif
            @if ($delivery->tracking_reference)
                <p>Tracking: {{ $delivery->tracking_reference }}</p>
            @endif
            @if ($delivery->dispatched_at)
                <p>Dispatched {{ $delivery->dispatched_at->timezone('Asia/Kolkata')->format('d M Y H:i') }} Asia/Kolkata</p>
            @endif
            @if ($delivery->delivered_at)
                <p>Delivered {{ $delivery->delivered_at->timezone('Asia/Kolkata')->format('d M Y H:i') }} Asia/Kolkata</p>
            @endif
            @if ($delivery->recipient_confirmation)
                <p>Recipient confirmation: {{ $delivery->recipient_confirmation }}</p>
            @endif
            @if ($choices !== [])
                <form method="POST" action="{{ route('admin.rewards.deliveries.update', $qualification) }}">
                    @csrf
                    <label>
                        <span>Next status</span>
                        <select class="ds-field" name="status" required>
                            @foreach ($choices as $choice)
                                <option value="{{ $choice }}">{{ \App\Models\RewardDelivery::labelFor($choice) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input name="method" label="Courier or delivery method" :value="old('method', $delivery->method)" />
                    <x-ui.input name="tracking_reference" label="Tracking reference" :value="old('tracking_reference', $delivery->tracking_reference)" />
                    <x-ui.input name="dispatched_at" type="datetime-local" label="Dispatch date and time (Asia/Kolkata)" :value="old('dispatched_at', $delivery->dispatched_at?->timezone('Asia/Kolkata')->format('Y-m-d\TH:i'))" />
                    <x-ui.input name="delivered_at" type="datetime-local" label="Delivery date and time (Asia/Kolkata)" :value="old('delivered_at', $delivery->delivered_at?->timezone('Asia/Kolkata')->format('Y-m-d\TH:i'))" />
                    <x-ui.input name="recipient_confirmation" label="Recipient confirmation" :value="old('recipient_confirmation', $delivery->recipient_confirmation)" :error="$errors->first('recipient_confirmation')" />
                    <x-ui.textarea name="internal_notes" label="Internal delivery notes">{{ old('internal_notes', $delivery->internal_notes) }}</x-ui.textarea>
                    <x-ui.button type="submit">Save delivery</x-ui.button>
                </form>
            @else
                <p>No further status change is available.</p>
            @endif
            @if ($delivery->events->isNotEmpty())
                <x-ui.table>
                    <table>
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Admin</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($delivery->events as $event)
                            <tr>
                                <td>{{ $event->created_at?->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                                <td>{{ $event->user?->name ?? 'System' }}</td>
                                <td>{{ \App\Models\RewardDelivery::labelFor($event->from_status) }}</td>
                                <td>{{ \App\Models\RewardDelivery::labelFor($event->to_status) }}</td>
                                <td>{{ $event->note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif
</div>
@endsection
