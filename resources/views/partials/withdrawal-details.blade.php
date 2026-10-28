<dl class="ds-detail">
    <div><dt>Member</dt><dd>{{ $withdrawal->member_id }}</dd></div>
    <div><dt>Source wallet</dt><dd>{{ $withdrawal->source_wallet }}</dd></div>
    <div><dt>Gross amount</dt><dd>{{ \App\Support\MoneyDisplay::format($withdrawal->amount, $withdrawal->denomination) }}</dd></div>
    <div><dt>Charge</dt><dd>{{ $withdrawal->charge_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->charge_amount, $withdrawal->denomination) }}</dd></div>
    <div><dt>Net amount</dt><dd>{{ $withdrawal->net_amount === null ? 'Not configured' : \App\Support\MoneyDisplay::format($withdrawal->net_amount, $withdrawal->denomination) }}</dd></div>
    <div><dt>Network</dt><dd>{{ $withdrawal->network ?: 'Not configured' }}</dd></div>
    <div><dt>Public receiving address</dt><dd>{{ $withdrawal->receiving_address ?: 'Not recorded' }}</dd></div>
    <div><dt>Payout network</dt><dd>{{ $withdrawal->payout_network ?: 'Not recorded' }}</dd></div>
    <div><dt>Payout transaction ID</dt><dd>{{ $withdrawal->payout_txid ?: 'Not recorded' }}</dd></div>
    @if ($withdrawal->rejection_reason)
        <div><dt>Rejection reason</dt><dd>{{ $withdrawal->rejection_reason }}</dd></div>
    @endif
</dl>
<section>
    <h2>Status history</h2>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th>Actor</th>
                    <th>Reason</th>
                    <th>Recorded</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawal->statusEvents as $event)
                <tr>
                    <td>{{ $event->from_status ? \App\Support\WithdrawalStatus::label($event->from_status) : '—' }}</td>
                    <td>{{ \App\Support\WithdrawalStatus::label($event->to_status) }}</td>
                    <td>{{ $event->actor ?: '—' }}</td>
                    <td>{{ $event->reason ?: '—' }}</td>
                    <td>{{ $event->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="5">No status events recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
