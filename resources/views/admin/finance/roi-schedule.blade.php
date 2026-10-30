@extends('admin.layouts.app')
@section('title', 'Income Control')
@section('page-title', 'Income Control')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="ROI Management"
        title="Income Control"
        lead="Automatic ROI generation is disabled. This page does not register a task or create a transaction. The counts below separate demonstration purchases from the real purchases financial:daily can see."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.finance.roi-history') }}" variant="secondary">Open ROI History</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="ds-stat-grid">
        <x-ui.stat label="Package income status" :value="$control['enabled'] ? 'Enabled' : 'Disabled'" hint="Generation flag" />
        <x-ui.stat label="ROI engine status" :value="$control['enabled'] ? 'Enabled' : 'Disabled'" hint="Same generation flag" />
        <x-ui.stat label="Commission engine status" :value="$control['enabled'] ? 'Enabled' : 'Disabled'" hint="Same generation flag" />
        <x-ui.stat label="Scheduler status" :value="$scheduledTasks === 0 ? 'Not registered' : $scheduledTasks.' registered'" hint="Tasks registered for this application" />
        <x-ui.stat label="Current business date" :value="$control['business_date']" :hint="$control['timezone']" />
        <x-ui.stat label="Stored ROI records" :value="$roiCount" hint="Legacy rows plus package accruals" />
        <x-ui.stat label="Legacy financial actions" :value="$legacyEnabled ? 'Enabled' : 'Disabled'" hint="Does not credit a wallet" />
        <x-ui.stat label="Accelerated testing" :value="$accelerated ? 'Enabled' : 'Disabled'" hint="Schedule testing flag" />
    </div>

    <x-ui.card title="Execution history" subtitle="Rows appear only after package-income:accrue runs. This page does not create one.">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Record</th>
                        <th>Business date</th>
                        <th>Recorded</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                @foreach (['Last successful execution' => $control['last_success'], 'Last failed execution' => $control['last_failure'], 'Last blocked execution' => $control['last_blocked'], 'Last refused execution' => $control['last_refusal']] as $label => $run)
                    <tr>
                        <td>{{ $label }}</td>
                        @if ($run)
                            <td>{{ $run['business_date'] ?: 'Not stored' }}</td>
                            <td>{{ $run['recorded_at'] }}</td>
                            <td>{{ $run['message'] }}</td>
                        @else
                            <td colspan="3">No execution has been recorded.</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Current business date check" subtitle="Calculated for this page view. These figures are not stored credits and are not an execution record.">
        <div class="ds-stat-grid">
            <x-ui.stat label="Real daily-cycle ROI rows" :value="$control['real_pending_roi']" hint="financial:daily excludes demonstration purchases" />
            <x-ui.stat label="Real daily-cycle commission rows" :value="$control['real_pending_commissions']" hint="Same exclusion as the dry run" />
            <x-ui.stat label="Demonstration ROI rows" :value="$control['demonstration_pending_roi']" hint="Shown on Demo Income Preview. Not written by the daily command." />
            <x-ui.stat label="Demonstration commission rows" :value="$control['demonstration_pending_commissions']" hint="Shown on Demo Income Preview. Not written by the daily command." />
            <x-ui.stat label="Skipped or blocked lines" :value="$control['skipped_count']" hint="From the same read-only check" />
            <x-ui.stat label="Prepared schedule" :value="$control['schedule_time']" :hint="'Not registered. Chunk size '.$control['chunk_size']" />
        </div>
        @if ($control['skipped'] === [])
            <p>No skipped or blocked line was returned for this date.</p>
        @else
            <ul>
                @foreach ($control['skipped'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            @if ($control['skipped_count'] > count($control['skipped']))
                <p>{{ $control['skipped_count'] - count($control['skipped']) }} more lines were omitted from this page.</p>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card title="Wallet reconciliation" subtitle="Read from stored balances and ledger rows. No balance is changed.">
        <p>{{ $control['reconciliation']['members'] }} members were compared. {{ $control['reconciliation']['variance_members'] }} differ from recorded ROI or Working credits minus stored ledger debits. {{ $control['reconciliation']['negative_members'] }} have a negative balance. Duplicate stored references: {{ $control['reconciliation']['duplicate_references'] }}.</p>
        <p>No historical debit was created. Stored ledger rows: {{ $control['ledger_rows'] }}. Manual posting and withdrawals stay disabled.</p>
        <p>Package income is not queued. Failed queue jobs that mention it: {{ $control['failed_jobs']['package_income'] }}. Total failed jobs: {{ $control['failed_jobs']['total'] }}.</p>
        <p>Wallet ledger posting that moves a balance by itself is disabled. Package accrual writes its ledger row only inside the accrual transaction, and that command is disabled.</p>
    </x-ui.card>

    <x-ui.card title="Scheduler health" subtitle="package-income:accrue stays unregistered. financial:daily is on the 00:10 Asia/Kolkata schedule and posts only after automation is armed.">
        <div class="ds-stat-grid">
            <x-ui.stat label="Legacy package-income:accrue" value="Not registered" hint="Generation flag stays disabled. This page cannot turn it on." />
            <x-ui.stat label="financial:daily" :value="$control['daily_cycle_registered'] ? 'Registered' : 'Not registered'" hint="00:10 Asia/Kolkata. Posting waits for the automation arm and stops on the emergency switch." />
            <x-ui.stat label="ROI calculation" :value="$control['roi_calculation_enabled'] ? 'On' : 'Off'" hint="Required before an actual daily run stores an ROI row" />
            <x-ui.stat label="ROI wallet crediting" :value="$control['roi_wallet_crediting_enabled'] ? 'On' : 'Off'" hint="Moves the ROI wallet only when on" />
            <x-ui.stat label="Level calculation" :value="$control['level_calculation_enabled'] ? 'On' : 'Off'" hint="Required before an actual daily run stores a commission row" />
            <x-ui.stat label="Level wallet crediting" :value="$control['level_wallet_crediting_enabled'] ? 'On' : 'Off'" hint="Moves the working wallet only when on" />
        </div>
        <p>Prepared legacy command: {{ $control['scheduler']['command'] }}. It is not on the scheduler. Historical backfill is off. financial:daily reads the same package rules, skips every LOCAL-TEST purchase and every @local-test.invalid member, and does not backfill earlier dates. A dry run writes nothing. Wallet crediting stays off unless that switch is saved on Financial Controls.</p>
        <p><a href="{{ route('admin.finance.real-income') }}">Open Real Income</a> for non-demo purchases and their skipped reasons. <a href="{{ route('admin.demo-income') }}">Demo Income Preview</a> is separate and is not ROI History.</p>
    </x-ui.card>

    <x-ui.card title="Main database calculation preview" :subtitle="$calculation['stored_notice']">
        <form method="get" action="{{ route('admin.finance.roi-schedule') }}" class="ds-field-grid">
            <x-ui.input type="date" name="date" label="Business date (Asia/Kolkata)" value="{{ $calculation['valid'] ? $calculation['date'] : '' }}" />
            <div>
                <x-ui.button type="submit">Preview this date</x-ui.button>
            </div>
        </form>
        <p>
            <x-ui.button href="{{ route('admin.finance.roi-schedule', ['date' => '2026-10-28']) }}" variant="secondary" size="sm">28 Oct 2026</x-ui.button>
            <x-ui.button href="{{ route('admin.finance.roi-schedule', ['date' => '2026-11-06']) }}" variant="secondary" size="sm">6 Nov 2026</x-ui.button>
            <x-ui.button href="{{ route('admin.finance.roi-schedule', ['date' => '2026-11-07']) }}" variant="secondary" size="sm">7 Nov 2026</x-ui.button>
        </p>
        @if (! $calculation['valid'])
            <p>{{ $calculation['error'] }}</p>
        @else
            <p>Selected business date {{ $calculation['date'] }} {{ $calculation['timezone'] }}. Theoretical pool {{ $calculation['totals']['theoretical_pool'] }} USDT is the full daily pool and is not the payable commission. Payable commission {{ $calculation['totals']['payable_commission'] }} USDT. Pending ROI {{ $calculation['totals']['pending_roi'] }} USDT. Pending commission {{ $calculation['totals']['pending_commission'] }} USDT.</p>
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Sponsor</th>
                            <th>Package</th>
                            <th>Purchase</th>
                            <th>Approval</th>
                            <th>ROI window</th>
                            <th>Commission window</th>
                            <th>Daily ROI</th>
                            <th>Theoretical pool</th>
                            <th>Payable commission</th>
                            <th>Credited</th>
                            <th>Pending this date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($calculation['rows'] as $row)
                        <tr>
                            <td>{{ $row['member_id'] }}<br>{{ $row['member_name'] }}@if (! empty($row['demonstration'])) <x-ui.badge tone="warning">Demonstration</x-ui.badge>@endif</td>
                            <td>{{ $row['sponsor_id'] }}</td>
                            <td>{{ $row['package_name'] }}<br>{{ $row['package_code'] }}</td>
                            <td>{{ $row['currency'] === 'Not stored' ? 'Not stored' : $row['currency'].' '.$row['purchase_amount'] }}</td>
                            <td>{{ $row['approval_at'] }}</td>
                            <td>{{ $row['first_roi_date'] }} to {{ $row['last_roi_date'] }}</td>
                            <td>{{ $row['first_commission_date'] }} to {{ $row['last_commission_date'] }}</td>
                            <td>{{ $row['daily_roi'] }} USDT</td>
                            <td>{{ $row['theoretical_pool'] }} USDT</td>
                            <td>{{ $row['payable_commission'] }} USDT</td>
                            <td>ROI {{ $row['credited_roi'] }} USDT<br>Commission {{ $row['credited_commission'] }} USDT</td>
                            <td>ROI {{ $row['pending_roi'] }} USDT<br>Commission {{ $row['pending_commission'] }} USDT</td>
                            <td>
                                @foreach ($row['labels'] as $label)
                                    <x-ui.badge :tone="str_starts_with($label, 'Eligible') ? 'success' : (str_starts_with($label, 'Blocked') ? 'warning' : 'muted')">{{ $label }}</x-ui.badge>
                                @endforeach
                                @foreach ($row['reasons'] as $reason)
                                    <div>{{ $reason }}</div>
                                @endforeach
                            </td>
                        </tr>
                        @if ($row['levels'] !== [])
                            <tr>
                                <td colspan="13">
                                    <p>{{ $row['member_id'] }} level lines for {{ $row['business_date'] }}. The rate amount is the chart figure. Only the payable amount is an accrual preview. Neither amount is a wallet credit.</p>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Level</th>
                                                <th>Recipient</th>
                                                <th>Unlocked</th>
                                                <th>Rate amount</th>
                                                <th>Payable</th>
                                                <th>Status</th>
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($row['levels'] as $level)
                                            <tr>
                                                <td>{{ $level['level'] }}</td>
                                                <td>{{ $level['recipient_id'] }}</td>
                                                <td>{{ $level['unlocked_levels'] }}</td>
                                                <td>{{ $level['rate_amount'] }} USDT</td>
                                                <td>{{ $level['payable_amount'] }} USDT</td>
                                                <td>{{ $level['status'] }}</td>
                                                <td>{{ $level['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="13">No member rows were returned for this date.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-ui.table>
            <h3>Payable recipients</h3>
            @if ($calculation['recipients'] === [])
                <p>No recipient is payable on this date.</p>
            @else
                <ul>
                    @foreach ($calculation['recipients'] as $recipient)
                        <li>{{ $recipient['member_id'] }} {{ $recipient['member_name'] }}: {{ $recipient['amount'] }} USDT</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card title="Production readiness" subtitle="Blockers stay in place. This page cannot enable generation, register the scheduler, or backfill income.">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($control['checklist'] as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td><x-ui.badge :tone="$item['status'] === 'ready' ? 'success' : 'warning'">{{ $item['status'] === 'ready' ? 'Ready' : 'Blocker' }}</x-ui.badge></td>
                        <td>{{ $item['detail'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
