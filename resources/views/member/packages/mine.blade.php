@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">My Packages</h1>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Package</th><th class="p-3">USD</th><th class="p-3">Status</th><th class="p-3">Submitted</th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr class="border-t"><td class="p-3">{{ $purchase->package_name }}</td><td class="p-3">{{ $purchase->price_usd }}</td><td class="p-3">{{ ucfirst($purchase->status) }}</td><td class="p-3">{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-6 text-slate-500">You have not requested a package yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
