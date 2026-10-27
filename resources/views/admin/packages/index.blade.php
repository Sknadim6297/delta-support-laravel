@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="ds-page-title">All Packages</h1>
            <p class="ds-page-copy">Tour details are stored on each package. Purchases keep their own price snapshot.</p>
        </div>
        <a href="{{ route('admin.packages.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Add Package</a>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" aria-label="Search name or code" class="rounded-lg border border-slate-200 px-3 py-2">
        <select name="status" aria-label="Package status" class="rounded-lg border border-slate-200 px-3 py-2">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Code</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">Type</th>
                    <th class="p-3">USD price</th>
                    <th class="p-3">Destinations</th>
                    <th class="p-3">Purchases</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                <tr class="border-t border-slate-100">
                    <td class="p-3 font-semibold">{{ $package->code }}</td>
                    <td class="p-3">{{ $package->name }}</td>
                    <td class="p-3">{{ $package->typeLabel() }}</td>
                    <td class="p-3">USD {{ $package->price_usd }}</td>
                    <td class="p-3">{{ $package->destinations ?: '—' }}</td>
                    <td class="p-3">{{ $package->purchases_count }}</td>
                    <td class="p-3">{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <a class="font-semibold text-brand-700" href="{{ route('admin.packages.edit', $package) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.packages.status', $package) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $package->is_active ? 0 : 1 }}">
                                <button type="submit" class="font-semibold text-navy-900">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="p-6 text-slate-500" colspan="8">No packages yet. Add a package and set its USD price.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
