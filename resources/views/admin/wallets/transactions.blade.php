@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border bg-white p-6">
    <h1 class="mb-2 text-2xl font-bold text-navy-900">Wallet Transactions</h1>
    <p class="text-sm text-slate-600">New wallet movements are not recorded as a separate ledger. ROI History and Level Income History list the stored investment records, and they are not package credits.</p>
    <p class="mt-3 text-sm"><a class="font-semibold text-brand-700" href="{{ route('admin.finance.roi-history') }}">ROI History</a> · <a class="font-semibold text-brand-700" href="{{ route('admin.finance.level-income') }}">Level Income History</a></p>
</div>
@endsection
