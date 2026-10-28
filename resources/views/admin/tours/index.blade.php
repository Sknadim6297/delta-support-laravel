@extends('admin.layouts.app')
@section('title', 'Tour Packages')
@section('page-title', 'Tour Packages')
@section('content')
<div>
    <p class="ds-lead">Tour details are stored with each package. Booking fulfillment is not open yet.</p>
    <div>
        <table>
            <thead><tr><th>Package</th><th>Tour</th><th>Duration</th><th>Visible</th><th></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr><td>{{ $package->name }}</td><td>{{ $package->tour_title ?: '—' }}</td><td>{{ $package->duration ?: '—' }}</td><td>{{ $package->is_active ? 'Visible' : 'Hidden' }}</td><td><a href="{{ route('admin.packages.edit', $package) }}">Edit</a></td></tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="5">No tour packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
