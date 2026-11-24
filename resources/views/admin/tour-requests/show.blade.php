@extends('admin.layouts.app')
@section('title', 'Tour Request '.$tourRequest->id)
@section('page-title', 'Tour Request Details')
@section('content')
@php
    $mobile = preg_replace('/\D+/', '', (string) ($tourRequest->member?->mobile_no ?? ''));
@endphp
<div class="ds-finance">
    <x-ui.page-header
        kicker="Travel"
        title="Tour Request #{{ $tourRequest->id }}"
        lead="Enquiry management only. Internal notes are never shown to the member."
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.tour-requests.index')" variant="secondary">Back to list</x-ui.button>
            @if ($mobile !== '')
                <a class="ds-btn ds-btn-secondary" href="tel:{{ $mobile }}"><i class="ri-phone-line" aria-hidden="true"></i> Call</a>
                <a class="ds-btn ds-btn-secondary" href="https://wa.me/{{ $mobile }}" target="_blank" rel="noopener"><i class="ri-whatsapp-line" aria-hidden="true"></i> WhatsApp</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert type="success"><p>{{ session('status') }}</p></x-ui.alert>
    @endif

    <div class="ds-stat-grid">
        <x-ui.stat label="Status" :value="$tourRequest->statusLabel()" />
        <x-ui.stat label="Member ID" :value="$tourRequest->member_id" />
        <x-ui.stat label="Submitted" :value="$tourRequest->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?? '—'" />
        <x-ui.stat label="Contacted" :value="$tourRequest->contacted_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?? '—'" />
    </div>

    <div class="ds-dash-columns">
        <x-ui.card title="Customer">
            <dl class="ds-detail">
                <div><dt>Name</dt><dd>{{ $tourRequest->member?->member_name }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $tourRequest->member?->mobile_no }}</dd></div>
                <div><dt>Email</dt><dd>{{ $tourRequest->member?->email }}</dd></div>
                <div><dt>Account</dt><dd>{{ $tourRequest->member?->status }}</dd></div>
            </dl>
        </x-ui.card>
        <x-ui.card title="Approved package eligibility (review only)">
            @forelse ($packages as $package)
                <p>{{ $package['code'] }} — {{ $package['name'] }} @if($package['reviewed_at']) ({{ $package['reviewed_at'] }}) @endif</p>
            @empty
                <p>No approved package on record. Interest is still valid as an enquiry.</p>
            @endforelse
        </x-ui.card>
    </div>

    <x-ui.card title="Update request">
        <form method="POST" action="{{ route('admin.tour-requests.update', $tourRequest) }}" class="ds-mm-form">
            @csrf
            <div class="ds-mm-fields">
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach (['pending','contacted','confirmed','completed','cancelled'] as $option)
                            <option value="{{ $option }}" @selected(old('status', $tourRequest->status) === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assigned_admin_id">Assigned administrator</label>
                    <select id="assigned_admin_id" name="assigned_admin_id">
                        <option value="">Unassigned</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((string) old('assigned_admin_id', $tourRequest->assigned_admin_id) === (string) $admin->id)>{{ $admin->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="contacted_at">Contact date</label>
                    <input type="datetime-local" id="contacted_at" name="contacted_at"
                        value="{{ old('contacted_at', optional($tourRequest->contacted_at)->timezone('Asia/Kolkata')->format('Y-m-d\\TH:i')) }}">
                </div>
                <div style="grid-column:1/-1;">
                    <label for="note">Follow-up note (history)</label>
                    <input type="text" id="note" name="note" maxlength="2000" value="{{ old('note') }}" placeholder="Short note for the status change history">
                </div>
                <div style="grid-column:1/-1;">
                    <label for="admin_notes">Internal admin notes (never shown to member)</label>
                    <textarea id="admin_notes" name="admin_notes" rows="4" maxlength="5000">{{ old('admin_notes', $tourRequest->admin_notes) }}</textarea>
                </div>
            </div>
            <button type="submit" class="ds-btn ds-btn-primary">Save changes</button>
        </form>
    </x-ui.card>

    <x-ui.card title="Request history" subtitle="Immutable status change log">
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Note</th>
                        <th>Actor</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td>{{ $event->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                        <td>{{ $event->action }}</td>
                        <td>{{ $event->from_status ?? '—' }}</td>
                        <td>{{ $event->to_status ?? '—' }}</td>
                        <td>{{ $event->note }}</td>
                        <td>
                            @if ($event->actor_type === 'admin')
                                {{ $event->admin?->email ?? 'admin' }}
                            @elseif ($event->actor_type === 'member')
                                {{ $event->actor_member_id }}
                            @else
                                system
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
@endsection
