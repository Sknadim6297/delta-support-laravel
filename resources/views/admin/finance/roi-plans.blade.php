@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">ROI Plans</h1>
        <p class="ds-page-copy">Stored package flags only. No ROI percentage is saved on a package, and this page does not calculate or credit ROI.</p>
    </header>
    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <article class="ds-card">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Packages</p>
            <p class="mt-2 text-xl font-semibold text-navy-900">{{ $packages->count() }}</p>
        </article>
        <article class="ds-card">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Income-term snapshots</p>
            <p class="mt-2 text-xl font-semibold text-navy-900">{{ $incomeTermCount }}</p>
        </article>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900">
                <tr>
                    <th class="p-3">Package</th>
                    <th class="p-3">Type</th>
                    <th class="p-3">Price</th>
                    <th class="p-3">ROI flag</th>
                    <th class="p-3">Level income flag</th>
                    <th class="p-3">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                <tr class="border-t border-slate-200">
                    <td class="p-3 font-semibold">{{ $package->name }}</td>
                    <td class="p-3">{{ $package->typeLabel() }}</td>
                    <td class="p-3">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td class="p-3">{{ $package->roi_eligible ? 'Eligible' : 'Not eligible' }}</td>
                    <td class="p-3">{{ $package->level_commission_eligible ? 'Eligible' : 'Not eligible' }}</td>
                    <td class="p-3">{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-slate-500">No packages have been saved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
