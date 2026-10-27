@extends('admin.layouts.app')
@section('content')
<div>
    <h1>Tour Packages</h1>
    <p>Tour details are stored with each package. Booking fulfillment is not open yet.</p>
    <div>
        <table>
            <thead><tr><th>Package</th><th>Tour</th><th>Duration</th><th>Visible</th><th></th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr><td>{{ $package->name }}</td><td>{{ $package->tour_title ?: '—' }}</td><td>{{ $package->duration ?: '—' }}</td><td>{{ $package->is_active ? 'Visible' : 'Hidden' }}</td><td><a href="{{ route('admin.packages.edit', $package) }}">Edit</a></td></tr>
            @empty
                <tr><td colspan="5">No tour packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
