@extends('admin.layouts.app')
@section('title', 'All Packages')
@section('page-title', 'All Packages')
@section('content')
<div>
    <x-ui.page-header title="All Packages" lead="Tour details are stored on each package. Purchases keep their own price snapshot.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.packages.create')">Add Package</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" aria-label="Search name or code">
        <select name="status" aria-label="Package status">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Package price</th>
                    <th>Destinations</th>
                    <th>Purchases</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                <tr>
                    <td>{{ $package->code }}</td>
                    <td>{{ $package->name }}</td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td>{{ $package->destinations ?: '—' }}</td>
                    <td>{{ $package->purchases_count }}</td>
                    <td>{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <div>
                            <a href="{{ route('admin.packages.edit', $package) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.packages.status', $package) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $package->is_active ? 0 : 1 }}">
                                <button type="submit">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No packages yet. Add a package and set its USDT price.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
