@extends('admin.layouts.app')
@section('title', 'Real Income')
@section('page-title', 'Real Income')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="ROI Management"
        title="Real Income"
        lead="Approved purchases that are not demonstration records. financial:daily counts only these rows. This page does not change an approval date, store an accrual, or credit a wallet."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.finance.roi-history') }}" variant="secondary">ROI History</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if (! $calculation['valid'])
        <p>{{ $calculation['error'] }}</p>
    @else
        <div class="ds-stat-grid">
            <x-ui.stat label="Business date" :value="$calculation['date']" :hint="$calculation['timezone']" />
            <x-ui.stat label="Real ROI rows" :value="$realRoi" hint="Dry-run count for this date" />
            <x-ui.stat label="Real commission rows" :value="$realCommissions" hint="Dry-run count for this date" />
            <x-ui.stat label="Real purchases listed" :value="count($rows)" hint="Demonstration purchases are omitted" />
        </div>
        <form method="get" action="{{ route('admin.finance.real-income') }}" class="ds-field-grid">
            <x-ui.input type="date" name="date" label="Business date (Asia/Kolkata)" value="{{ $calculation['date'] }}" />
            <div>
                <x-ui.button type="submit">Check this date</x-ui.button>
            </div>
        </form>
        <x-ui.table>
            <table class="ds-table-wide">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Approval</th>
                        <th>First earning date</th>
                        <th>ROI window</th>
                        <th>Commission window</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['member_id'] }}<br>{{ $row['member_name'] }}<br><small>{{ $row['sponsor_id'] }}</small></td>
                        <td>{{ $row['package_code'] }}<br>{{ $row['payment_reference'] }}</td>
                        <td>{{ $row['approval_at'] }}</td>
                        <td>{{ $row['first_roi_date'] }}</td>
                        <td>{{ $row['first_roi_date'] }} to {{ $row['last_roi_date'] }}</td>
                        <td>{{ $row['first_commission_date'] }} to {{ $row['last_commission_date'] }}</td>
                        <td>
                            @foreach ($row['labels'] as $label)
                                <x-ui.badge :tone="str_starts_with($label, 'Eligible') ? 'success' : 'warning'">{{ $label }}</x-ui.badge>
                            @endforeach
                            @foreach ($row['reasons'] as $reason)
                                <div>{{ $reason }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No non-demo purchase is stored. financial:daily --dry-run returns zero ROI rows and zero commission rows for that reason.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-ui.table>
        @if ($skipped !== [])
            <h2>Additional lines from the real daily plan</h2>
            <ul>
                @foreach ($skipped as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
@endsection
