@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-5">
        <h1 class="ds-page-title">My Packages</h1>
        <p class="ds-page-copy">Each row is a separate purchase. The USD amount is the price saved when the request was created.</p>
    </header>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Package</th>
                    <th class="p-3">Reference</th>
                    <th class="p-3">Purchased USD</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Purchase date</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr class="border-t border-slate-100">
                    <td class="p-3">
                        <div class="font-semibold text-navy-900">{{ $purchase->package_name }}</div>
                        <div class="text-slate-500">{{ $purchase->package_code }}</div>
                    </td>
                    <td class="p-3">{{ $purchase->reference }}</td>
                    <td class="p-3">USD {{ $purchase->price_usd }}</td>
                    <td class="p-3">{{ $purchase->statusLabel() }}</td>
                    <td class="p-3">{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                    <td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('member.payments.show', $purchase) }}">View details</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-slate-500">You have not requested a package yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
