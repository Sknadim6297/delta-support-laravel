@extends('admin.layouts.app')
@section('title', 'Sub-Admin Accounts')
@section('page-title', 'Sub-Admin Accounts')
@section('content')
<div class="ds-finance ds-subadmin">
    <x-ui.page-header
        kicker="Security"
        title="Sub-Admin Management"
        lead="Delegate panel access with module-level permissions. Only the Main Admin can create and manage Sub-Admin accounts."
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.sub-admins.create')">Create Sub-Admin</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert type="success"><p>{{ session('status') }}</p></x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form class="ds-mm-filters" method="GET" action="{{ route('admin.sub-admins.index') }}">
        <div>
            <label for="subadmin-q">Search</label>
            <input id="subadmin-q" name="q" value="{{ $search }}" placeholder="Name, login ID, or email">
        </div>
        <div>
            <label for="subadmin-status">Status</label>
            <select id="subadmin-status" name="status">
                <option value="">All</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
        </div>
        <button class="ds-btn" type="submit">Filter</button>
        @if ($search !== '' || $status !== '')
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.sub-admins.index') }}">Clear</a>
        @endif
    </form>

    <x-ui.card title="Sub-Admin accounts" subtitle="Assigned permissions control sidebar visibility and route access." flush>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Full Name</th>
                        <th>Login ID</th>
                        <th>Email</th>
                        <th>Module count</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($subAdmins as $row)
                    <tr>
                        <td>{{ ($subAdmins->firstItem() ?? 1) + $loop->index }}</td>
                        <td>{{ $row->name }}</td>
                        <td><span class="ds-mm-id">{{ $row->login_id }}</span></td>
                        <td>{{ $row->email }}</td>
                        <td>{{ (int) ($row->admin_permissions_count ?? 0) }}</td>
                        <td>
                            <x-ui.badge :tone="$row->is_active ? 'success' : 'muted'">
                                {{ $row->is_active ? 'Active' : 'Inactive' }}
                            </x-ui.badge>
                        </td>
                        <td>
                            @if ($row->last_login_at)
                                {{ $row->last_login_at->timezone('Asia/Kolkata')->format('d M Y, H:i') }}
                            @else
                                <span class="ds-muted">Never</span>
                            @endif
                        </td>
                        <td>{{ $row->created_at?->timezone('Asia/Kolkata')->format('d M Y') ?? '—' }}</td>
                        <td>
                            <div class="ds-row-actions">
                                <x-ui.button size="sm" variant="secondary" :href="route('admin.sub-admins.show', $row)">View</x-ui.button>
                                <x-ui.button size="sm" variant="secondary" :href="route('admin.sub-admins.edit', $row)">Edit</x-ui.button>
                                <x-ui.button size="sm" variant="outline" :href="route('admin.sub-admins.permissions', $row)">Permissions</x-ui.button>
                                <x-ui.button size="sm" variant="outline" :href="route('admin.sub-admins.password.edit', $row)">Reset password</x-ui.button>
                                @if ($row->is_active)
                                    <form method="POST" action="{{ route('admin.sub-admins.deactivate', $row) }}" onsubmit="return confirm('Deactivate this Sub-Admin and revoke active sessions?');">
                                        @csrf
                                        <x-ui.button type="submit" size="sm" variant="danger">Deactivate</x-ui.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.sub-admins.activate', $row) }}">
                                        @csrf
                                        <x-ui.button type="submit" size="sm">Activate</x-ui.button>
                                    </form>
                                @endif
                                @php
                                    $deleteConfirm = 'Delete Sub-Admin '.$row->name.' (Login ID: '.$row->login_id.")?\n\nThis removes the account from Sub-Admin Management and immediately revokes access. Historical audit and financial records are preserved.";
                                @endphp
                                <form method="POST" action="{{ route('admin.sub-admins.destroy', $row) }}" onsubmit="return confirm(@js($deleteConfirm));">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" size="sm" variant="danger">Delete</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="ds-empty-cell" colspan="9">No Sub-Admin accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ds-mm-pages">{{ $subAdmins->links('pagination::bootstrap-5') }}</div>
    </x-ui.card>
</div>
@endsection