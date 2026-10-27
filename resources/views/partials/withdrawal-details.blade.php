<dl class="ds-card grid gap-3 sm:grid-cols-2">
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Member</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->member_id }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Source wallet</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->source_wallet }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Gross amount</dt><dd class="font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Charge</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Net amount</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Network</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->network ?: 'Not configured' }}</dd></div>
    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Public receiving address</dt><dd class="break-all font-semibold text-navy-900">{{ $withdrawal->receiving_address ?: 'Not recorded' }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Payout network</dt><dd class="font-semibold text-navy-900">{{ $withdrawal->payout_network ?: 'Not recorded' }}</dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Payout transaction ID</dt><dd class="break-all font-semibold text-navy-900">{{ $withdrawal->payout_txid ?: 'Not recorded' }}</dd></div>
    @if ($withdrawal->rejection_reason)
        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-ink-500">Rejection reason</dt><dd class="text-navy-900">{{ $withdrawal->rejection_reason }}</dd></div>
    @endif
</dl>
<section class="mt-4">
    <h2 class="mb-2 text-lg font-semibold text-navy-900">Status history</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">From</th><th class="p-3">To</th><th class="p-3">Actor</th><th class="p-3">Reason</th><th class="p-3">Recorded</th></tr></thead>
            <tbody>
            @forelse ($withdrawal->statusEvents as $event)
                <tr class="border-t border-slate-200">
                    <td class="p-3">{{ $event->from_status ? \App\Support\WithdrawalStatus::label($event->from_status) : '—' }}</td>
                    <td class="p-3">{{ \App\Support\WithdrawalStatus::label($event->to_status) }}</td>
                    <td class="p-3">{{ $event->actor ?: '—' }}</td>
                    <td class="p-3">{{ $event->reason ?: '—' }}</td>
                    <td class="p-3">{{ $event->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-slate-500">No status events recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
