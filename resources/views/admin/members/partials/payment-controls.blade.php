@php
    $control = $paymentControl;
    $master = old('master_enabled', $control?->master_enabled ?? true);
    $roiPref = old('roi_preference', $control?->roi_preference ?? true);
    $levelPref = old('level_preference', $control?->level_preference ?? true);
    $status = $control?->status ?? 'active';
    $statusLabel = $control?->statusLabel() ?? 'Active';
    $tone = match ($status) {
        'stopped' => 'danger',
        'partial' => 'warning',
        default => 'success',
    };
@endphp
<section class="ds-report-block" id="memberPaymentControls">
    <header class="ds-dash-head">
        <h2>Payment Controls</h2>
        <p>
            Recipient-only ROI and Level Income stops. Does not change wallets, history, activation, referrals, or withdrawals.
            Financial:daily applies these blocks only when integration is authorized
            (currently {{ !empty($paymentIntegrationAuthorized) ? 'AUTHORIZED' : 'NOT authorized' }}).
        </p>
    </header>

    <div class="ds-mm-stats" style="margin-bottom:1rem;">
        <article class="ds-mm-stat">
            <span>Payment status</span>
            <strong><x-ui.badge :tone="$tone">{{ $statusLabel }}</x-ui.badge></strong>
        </article>
        <article class="ds-mm-stat">
            <span>Last modified by</span>
            <strong>{{ $control?->updater?->email ?? '—' }}</strong>
        </article>
        <article class="ds-mm-stat">
            <span>Last effective from</span>
            <strong>{{ optional($control?->last_effective_from)->toDateString() ?? '—' }}</strong>
        </article>
        <article class="ds-mm-stat">
            <span>Suggested effective date</span>
            <strong>{{ $suggestedPaymentEffectiveFrom ?? now('Asia/Kolkata')->toDateString() }}</strong>
        </article>
    </div>

    @if (session('status') && request()->routeIs('admin.members.update'))
        <x-ui.alert type="success"><p>{{ session('status') }}</p></x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.members.payment-controls', $member->member_id) }}" id="paymentControlForm" class="ds-mm-form">
        @csrf
        <input type="hidden" name="master_enabled" id="pcMasterValue" value="{{ $master ? '1' : '0' }}">
        <input type="hidden" name="roi_preference" id="pcRoiValue" value="{{ $roiPref ? '1' : '0' }}">
        <input type="hidden" name="level_preference" id="pcLevelValue" value="{{ $levelPref ? '1' : '0' }}">

        <div class="ds-mm-fields">
            <div>
                <label>
                    <input type="checkbox" id="pcMasterToggle" @checked($master)>
                    All Income Payment (master)
                </label>
                <p class="ds-muted">Master OFF stops ROI and Level without clearing individual preferences. Master ON restores those preferences.</p>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="pcRoiToggle" @checked($roiPref)>
                    ROI Payment preference
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="pcLevelToggle" @checked($levelPref)>
                    Level Income preference
                </label>
            </div>
            <div>
                <label for="pcEffectiveFrom">Effective business date (Asia/Kolkata)</label>
                <input type="date" id="pcEffectiveFrom" name="effective_from" required
                    value="{{ old('effective_from', $suggestedPaymentEffectiveFrom ?? now('Asia/Kolkata')->toDateString()) }}">
            </div>
            <div style="grid-column:1/-1;">
                <label for="pcReason">Reason <span>*</span></label>
                <textarea id="pcReason" name="reason" rows="2" required maxlength="2000" placeholder="Mandatory reason for this change">{{ old('reason') }}</textarea>
            </div>
        </div>

        <label style="display:block;margin:0.75rem 0;">
            <input type="checkbox" name="confirm" value="1" required>
            I confirm this change. Previously credited income will not be reversed. Stopped calendar days consume the package window (no extension, no backfill).
        </label>

        <div class="ds-btn-row" style="gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="ds-btn ds-btn-primary" id="pcSubmitBtn">Save payment controls</button>
        </div>
    </form>

    <div class="ds-dash-head" style="margin-top:1.25rem;">
        <h3>Payment control history</h3>
        <p>Immutable admin actions for this member.</p>
    </div>
    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>Effective from</th>
                    <th>Reason</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($paymentEvents as $event)
                <tr>
                    <td>{{ $event->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td>{{ $event->action }}</td>
                    <td>{{ optional($event->effective_from)->toDateString() }}</td>
                    <td>{{ $event->reason }}</td>
                    <td>{{ $event->actor?->email ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No payment control changes yet. Default is all payments enabled.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
    const master = document.getElementById('pcMasterToggle');
    const roi = document.getElementById('pcRoiToggle');
    const level = document.getElementById('pcLevelToggle');
    const masterVal = document.getElementById('pcMasterValue');
    const roiVal = document.getElementById('pcRoiValue');
    const levelVal = document.getElementById('pcLevelValue');
    const form = document.getElementById('paymentControlForm');
    if (!master || !form) return;

    const sync = () => {
        masterVal.value = master.checked ? '1' : '0';
        roiVal.value = roi.checked ? '1' : '0';
        levelVal.value = level.checked ? '1' : '0';
        roi.disabled = !master.checked;
        level.disabled = !master.checked;
    };
    master.addEventListener('change', sync);
    roi.addEventListener('change', sync);
    level.addEventListener('change', sync);
    sync();

    form.addEventListener('submit', (e) => {
        sync();
        const turningOff = !master.checked || !roi.checked || !level.checked;
        const msg = turningOff
            ? 'Stop or partially stop payments from the effective business date? Past credits stay unchanged.'
            : 'Resume / update payment preferences from the effective business date?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });
})();
</script>
