@extends('admin.layouts.app')
@section('title', 'Demo Income Preview')
@section('page-title', 'Demo Income Preview')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Demonstration"
        title="Demo Income Preview"
        lead="This preview uses the stored eligibility rules for demonstration members only. It is not ROI History and it is not included in financial:daily. It does not credit a wallet, create an accrual, or send a payout."
    />
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Sponsor</th>
                    <th>Package</th>
                    <th class="ds-money">Qualifying directs</th>
                    <th>Unlocked levels</th>
                    <th class="ds-money">ROI wallet</th>
                    <th class="ds-money">Working wallet</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tree as $member)
                <tr>
                    <td>{{ $member['member_id'] }} · {{ $member['name'] }}</td>
                    <td>{{ $member['sponsor_id'] }} · {{ $member['sponsor_name'] }}</td>
                    <td>{{ $member['package'] === '' ? 'None' : $member['package'] }}</td>
                    <td class="ds-money">{{ $member['qualifying_directs'] }}</td>
                    <td>{{ $member['unlocked_levels'] }}</td>
                    <td class="ds-money">{{ $member['roi_wallet'] }}</td>
                    <td class="ds-money">{{ $member['working_wallet'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No demonstration members are stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <h2>ROI preview for {{ $preview['date'] }}</h2>
    <x-ui.table>
        <table>
            <thead><tr><th>Member</th><th>Package</th><th class="ds-money">Amount</th></tr></thead>
            <tbody>
            @forelse ($preview['roi'] as $row)
                <tr>
                    <td>{{ $row['member_id'] }}</td>
                    <td>{{ $row['package_code'] }}</td>
                    <td class="ds-money">{{ $row['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No demonstration ROI is eligible on this date.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <h2>Level income preview for {{ $preview['date'] }}</h2>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead><tr><th>Receiver</th><th>From</th><th>Level</th><th class="ds-money">Amount</th></tr></thead>
            <tbody>
            @forelse ($preview['commissions'] as $row)
                <tr>
                    <td>{{ $row['member_id'] }}</td>
                    <td>{{ $row['from_member_id'] }}</td>
                    <td>{{ $row['level'] }}</td>
                    <td class="ds-money">{{ $row['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No demonstration level income is eligible on this date.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
</div>
@endsection
