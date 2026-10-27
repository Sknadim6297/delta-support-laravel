@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>ROI Plans</h1>
        <p>Stored package flags only. No ROI percentage is saved on a package, and this page does not calculate or credit ROI.</p>
    </header>
    <div>
        <article>
            <p>Packages</p>
            <p>{{ $packages->count() }}</p>
        </article>
        <article>
            <p>Income-term snapshots</p>
            <p>{{ $incomeTermCount }}</p>
        </article>
    </div>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>ROI flag</th>
                    <th>Level income flag</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                <tr>
                    <td>{{ $package->name }}</td>
                    <td>{{ $package->typeLabel() }}</td>
                    <td>{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</td>
                    <td>{{ $package->roi_eligible ? 'Eligible' : 'Not eligible' }}</td>
                    <td>{{ $package->level_commission_eligible ? 'Eligible' : 'Not eligible' }}</td>
                    <td>{{ $package->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No packages have been saved.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
