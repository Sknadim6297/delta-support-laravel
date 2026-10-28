@extends('admin.layouts.app')
@section('title', 'Wallet Transactions')
@section('page-title', 'Wallet Transactions')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        title="Wallet Transactions"
        lead="New wallet movements are not recorded as a separate ledger. ROI History and Level Income History list the stored investment records, and they are not package credits."
    />
    <x-ui.card title="Where to review stored records" subtitle="These lists do not create a credit or a debit.">
        <div class="ds-actions">
            <x-ui.button href="{{ route('admin.finance.roi-history') }}" variant="secondary">ROI History</x-ui.button>
            <x-ui.button href="{{ route('admin.finance.level-income') }}" variant="secondary">Level Income History</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
