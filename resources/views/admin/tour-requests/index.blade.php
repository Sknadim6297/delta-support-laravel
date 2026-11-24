@extends('admin.layouts.app')
@section('title', 'Tour Requests')
@section('page-title', 'Tour Requests')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Travel"
        title="Tour Requests"
        lead="Customer tour interest and callback management. Requests are enquiries only — not bookings or payments."
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.tour-requests.export', request()->query())" variant="secondary">Export CSV</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <x-ui.stat label="Total Requests" :value="(string) $kpis['total']" />
        <x-ui.stat label="Pending Requests" :value="(string) $kpis['pending']" tone="warning" />
        <x-ui.stat label="Contacted Customers" :value="(string) $kpis['contacted']" />
        <x-ui.stat label="Confirmed Tours" :value="(string) $kpis['confirmed']" tone="success" />
        <x-ui.stat label="Completed Requests" :value="(string) $kpis['completed']" tone="success" />
    </div>

    <form class="ds-mm-filters" method="GET" action="{{ route('admin.tour-requests.index') }}">
        <div>
            <label for="tour-q">Search</label>
            <input id="tour-q" name="q" value="{{ $search }}" placeholder="Member ID, name, email, mobile">
        </div>
        <div>
            <label for="tour-status">Status</label>
            <select id="tour-status" name="status">
                <option value="">All</option>
                @foreach (['pending','contacted','confirmed','completed','cancelled'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </div>
        <button class="ds-btn" type="submit">Filter</button>
        @if ($search !== '' || $status !== '')
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.tour-requests.index') }}">Clear</a>
        @endif
    </form>

    <x-ui.card title="Requests" subtitle="Uses saved member profile details" flush>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Approved packages</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($requests as $row)
                    @php
                        $mobile = preg_replace('/\D+/', '', (string) ($row->member?->mobile_no ?? ''));
                        $packages = $packageMap[$row->member_id] ?? [];
                    @endphp
                    <tr>
                        <td><span class="ds-mm-id">{{ $row->member_id }}</span></td>
                        <td>{{ $row->member?->member_name }}</td>
                        <td>{{ $row->member?->mobile_no }}</td>
                        <td>{{ $row->member?->email }}</td>
                        <td>
                            @forelse ($packages as $package)
                                <div>{{ $package['code'] }} — {{ $package['name'] }}</div>
                            @empty
                                <span class="ds-muted">No approved package</span>
                            @endforelse
                        </td>
                        <td>{{ $row->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                        <td><x-ui.badge :tone="$row->statusTone()">{{ $row->statusLabel() }}</x-ui.badge></td>
                        <td>{{ $row->assignee?->email ?? '—' }}</td>
                        <td>
                            <div class="ds-row-actions">
                                <x-ui.button size="sm" variant="secondary" :href="route('admin.tour-requests.show', $row)">View Details</x-ui.button>
                                @if ($mobile !== '')
                                    <a class="ds-btn ds-btn-secondary" style="padding:.25rem .5rem;" href="tel:{{ $mobile }}" title="Call"><i class="ri-phone-line" aria-hidden="true"></i></a>
                                    <a class="ds-btn ds-btn-secondary" style="padding:.25rem .5rem;" href="https://wa.me/{{ $mobile }}" target="_blank" rel="noopener" title="WhatsApp"><i class="ri-whatsapp-line" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="ds-empty-cell" colspan="9">No tour interest requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ds-mm-pages">{{ $requests->links('pagination::bootstrap-5') }}</div>
    </x-ui.card>
</div>
@endsection
