@extends('admin.layouts.app')
@section('title', 'All Packages')
@section('page-title', 'All Packages')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header title="Package Management" lead="Edit package price, Daily ROI and Auto ROI. Saving or toggling never credits wallets.">
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

    <div class="ds-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Package Name</th>
                    <th>Package Price (USDT)</th>
                    <th>Daily ROI (USDT)</th>
                    <th>ROI Days</th>
                    <th>Monthly Return (USDT)</th>
                    <th>Total Return (USDT)</th>
                    <th>Auto ROI</th>
                    <th>Package Active/Inactive</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                @php
                    $managed = $adminRoiById[$package->id] ?? ['daily' => null, 'days' => null];
                    $daily = $managed['daily'] ?? null;
                    $days = $managed['days'] ?? null;
                    $monthly = $daily ? \App\Support\PackageReturnDisplay::monthlyReturn((string) $daily) : null;
                    $total = ($daily && $days) ? \App\Support\PackageReturnDisplay::totalReturn((string) $daily, (int) $days) : null;
                    $auto = $autoRoiById[$package->id] ?? null;
                    $tourOnly = $package->package_type === \App\Models\Package::TYPE_TOUR;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.packages.edit', $package) }}">{{ $package->name }}</a>
                        <div><small>{{ $package->code }}</small></div>
                    </td>
                    <td>{{ \App\Support\MoneyDisplay::format($package->price_usd, 'USDT') }}</td>
                    <td>{{ $tourOnly ? '—' : ($daily ? $daily.' USDT' : 'Not set') }}</td>
                    <td>{{ $tourOnly ? '—' : ($days ?? '—') }}</td>
                    <td>{{ $tourOnly ? '—' : ($monthly ? $monthly.' USDT' : '—') }}</td>
                    <td>{{ $tourOnly ? '—' : ($total ? $total.' USDT' : '—') }}</td>
                    <td>
                        @if ($auto)
                            @php
                                $autoTone = match ($auto['code'] ?? '') {
                                    'on_scheduled', 'on_blocked_global' => 'success',
                                    'off', 'disabled' => 'muted',
                                    default => 'warning',
                                };
                            @endphp
                            <x-ui.badge :tone="$autoTone">{{ $auto['label'] }}</x-ui.badge>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <x-ui.badge :tone="$package->is_active ? 'success' : 'muted'">{{ $package->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                    </td>
                    <td><a href="{{ route('admin.packages.edit', $package) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
