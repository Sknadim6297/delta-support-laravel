@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="mb-2 text-2xl font-bold text-navy-900">Tour Packages</h1>
    <p class="mb-4 text-sm text-slate-600">Tour details are stored with each package. Booking fulfillment is not open yet.</p>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Package</th><th class="p-3">Tour</th><th class="p-3">Duration</th><th class="p-3">Visible</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr class="border-t"><td class="p-3">{{ $package->name }}</td><td class="p-3">{{ $package->tour_title ?: '—' }}</td><td class="p-3">{{ $package->duration ?: '—' }}</td><td class="p-3">{{ $package->is_active ? 'Visible' : 'Hidden' }}</td><td class="p-3"><a class="font-semibold text-brand-700" href="{{ route('admin.packages.edit', $package) }}">Edit</a></td></tr>
            @empty
                <tr><td colspan="5" class="p-6 text-slate-500">No tour packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
