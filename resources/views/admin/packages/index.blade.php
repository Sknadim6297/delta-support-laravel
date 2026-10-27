@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-navy-900">All Packages</h1>
        <a href="{{ route('admin.packages.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Add Package</a>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" class="rounded-lg border border-slate-200 px-3 py-2">
        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Code</th><th class="p-3">Name</th><th class="p-3">USD price</th><th class="p-3">Tour</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $package->code }}</td>
                    <td class="p-3">{{ $package->name }}</td>
                    <td class="p-3">{{ $package->price_usd }}</td>
                    <td class="p-3">{{ $package->tour_title ?: '—' }}</td>
                    <td class="p-3">{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('admin.packages.edit', $package) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="6">No packages yet. Add a package and set its USD price.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
