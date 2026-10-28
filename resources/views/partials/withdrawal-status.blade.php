@php
    $tone = match ($status) {
        'approved', 'completed' => 'success',
        'pending_review', 'processing', 'draft' => 'warning',
        'rejected', 'failed' => 'danger',
        'cancelled' => 'muted',
        default => 'brand',
    };
@endphp
<x-ui.badge :tone="$tone">{{ \App\Support\WithdrawalStatus::label($status) }}</x-ui.badge>
