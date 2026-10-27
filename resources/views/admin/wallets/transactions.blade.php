@extends('admin.layouts.app')
@section('content')
<div>
    <h1>Wallet Transactions</h1>
    <p>New wallet movements are not recorded as a separate ledger. ROI History and Level Income History list the stored investment records, and they are not package credits.</p>
    <p><a href="{{ route('admin.finance.roi-history') }}">ROI History</a> · <a href="{{ route('admin.finance.level-income') }}">Level Income History</a></p>
</div>
@endsection
