@extends('admin.layouts.app')
@php
    $permissionSet = array_fill_keys($permissions, true);
    $assignedByModule = [];
    foreach ($modules as $moduleRow) {
        $labels = [];
        foreach ($moduleRow['permissions'] as $definition) {
            if (isset($permissionSet[$definition['key']])) {
                $labels[] = $definition['label'];
            }
        }
        if ($labels !== []) {
            $assignedByModule[$moduleRow['module']] = $labels;
        }
    }
    $actionLabels = [
        'sub_admin_created' => 'Account created',
        'sub_admin_updated' => 'Account updated',
        'sub_admin_password_reset' => 'Password reset',
        'sub_admin_activated' => 'Activated',
        'sub_admin_deactivated' => 'Deactivated',
        'sub_admin_deleted' => 'Deleted',
        'admin_login' => 'Signed in',
        'admin_logout' => 'Signed out',
    ];
@endphp
@section('title', $subAdmin->name)
@section('page-title', $subAdmin->name)
@section('content')
<div class="ds-catalog ds-subadmin">
    <x-ui.page-header
        kicker="Security"
        :title="$subAdmin->name"
        :lead="'Login ID '.$subAdmin->login_id"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.sub-admins.edit', $subAdmin)" variant="secondary">Edit</x-ui.button>
            <x-ui.button :href="route('admin.sub-admins.permissions', $subAdmin)" variant="secondary">Permissions</x-ui.button>
            <x-ui.button :href="route('admin.sub-admins.password.edit', $subAdmin)" variant="outline">Reset password</x-ui.button>
            <x-ui.button :href="route('admin.sub-admins.index')" variant="outline">All Sub-Admins</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert type="success"><p>{{ session('status') }}</p></x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="ds-stat-grid">
        <x-ui.stat label="Status" :value="$subAdmin->is_active ? 'Active' : 'Inactive'" :tone="$subAdmin->is_active ? 'success' : 'muted'" />
        <x-ui.stat label="Permissions" :value="(string) (int) ($subAdmin->admin_permissions_count ?? count($permissions))" />
        <x-ui.stat
            label="Last login"
            :value="$subAdmin->last_login_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?? 'Never'"
        />
        <x-ui.stat label="Created" :value="$subAdmin->created_at?->timezone('Asia/Kolkata')->format('d M Y') ?? '—'" />
    </div>

    <x-ui.card title="Account information">
        <dl class="ds-subadmin-dl">
            <div><dt>Full name</dt><dd>{{ $subAdmin->name }}</dd></div>
            <div><dt>Login ID</dt><dd><span class="ds-mm-id">{{ $subAdmin->login_id }}</span></dd></div>
            <div><dt>Email</dt><dd>{{ $subAdmin->email }}</dd></div>
            <div>
                <dt>Status</dt>
                <dd>
                    <x-ui.badge :tone="$subAdmin->is_active ? 'success' : 'muted'">
                        {{ $subAdmin->is_active ? 'Active' : 'Inactive' }}
                    </x-ui.badge>
                </dd>
            </div>
        </dl>
        <div class="ds-row-actions" style="margin-top:1rem;">
            @if ($subAdmin->is_active)
                <form method="POST" action="{{ route('admin.sub-admins.deactivate', $subAdmin) }}" onsubmit="return confirm('Deactivate this Sub-Admin and revoke active sessions?');">
                    @csrf
                    <x-ui.button type="submit" variant="danger">Deactivate</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.sub-admins.activate', $subAdmin) }}">
                    @csrf
                    <x-ui.button type="submit">Activate</x-ui.button>
                </form>
            @endif
            @php
                $deleteConfirm = 'Delete Sub-Admin '.$subAdmin->name.' (Login ID: '.$subAdmin->login_id.")?\n\nThis removes the account from Sub-Admin Management and immediately revokes access. Historical audit and financial records are preserved.";
            @endphp
            <form method="POST" action="{{ route('admin.sub-admins.destroy', $subAdmin) }}" onsubmit="return confirm(@js($deleteConfirm));">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">Delete Sub-Admin</x-ui.button>
            </form>
        </div>
    </x-ui.card>

    <x-ui.card title="Assigned permissions" subtitle="Grouped by panel module">
        @if ($assignedByModule === [])
            <p class="ds-muted">No permissions assigned. This account will land on Access not assigned after sign-in.</p>
        @else
            <div class="ds-subadmin-assigned-grid">
                @foreach ($assignedByModule as $moduleName => $labels)
                    <section class="ds-subadmin-assigned-block">
                        <h3 class="ds-section-title" style="margin:0 0 6px;font-size:14px;">{{ $moduleName }}</h3>
                        <ul class="ds-subadmin-assigned-list">
                            @foreach ($labels as $label)
                                <li>{{ $label }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Recent activity" subtitle="Administration events and sign-ins (last 40)" flush>
        <div class="ds-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Event</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    @php
                        $filters = is_array($log->filters) ? $log->filters : [];
                        $detailParts = [];
                        if (isset($filters['before'], $filters['after']) && is_array($filters['before']) && is_array($filters['after'])) {
                            foreach (['name', 'login_id', 'email', 'is_active'] as $field) {
                                $before = $filters['before'][$field] ?? null;
                                $after = $filters['after'][$field] ?? null;
                                if ($before != $after) {
                                    $detailParts[] = $field.': '.(is_bool($before) ? ($before ? 'yes' : 'no') : (string) $before).' → '.(is_bool($after) ? ($after ? 'yes' : 'no') : (string) $after);
                                }
                            }
                            $beforePerms = $filters['before']['permissions'] ?? null;
                            $afterPerms = $filters['after']['permissions'] ?? null;
                            if (is_array($beforePerms) && is_array($afterPerms)) {
                                $added = count(array_diff($afterPerms, $beforePerms));
                                $removed = count(array_diff($beforePerms, $afterPerms));
                                if ($added > 0) {
                                    $detailParts[] = 'Added '.$added.' permission(s)';
                                }
                                if ($removed > 0) {
                                    $detailParts[] = 'Removed '.$removed.' permission(s)';
                                }
                            }
                        }
                        $detail = $detailParts !== [] ? implode(' · ', $detailParts) : '—';
                    @endphp
                    <tr>
                        <td>{{ $log->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') ?? '—' }}</td>
                        <td>{{ $log->user?->name ?? $log->user?->email ?? '—' }}</td>
                        <td>{{ $actionLabels[$log->action] ?? str_replace('_', ' ', $log->action) }}</td>
                        <td class="ds-subadmin-log-detail">{{ $detail }}</td>
                    </tr>
                @empty
                    <tr><td class="ds-empty-cell" colspan="4">No activity recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>

<style>
    .ds-subadmin-dl {
        display: grid;
        gap: 0.65rem 1.25rem;
        grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
        margin: 0;
    }
    .ds-subadmin-dl > div {
        display: grid;
        gap: 0.15rem;
    }
    .ds-subadmin-dl dt {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        opacity: 0.75;
        margin: 0;
    }
    .ds-subadmin-dl dd {
        margin: 0;
        font-weight: 600;
    }
    .ds-subadmin-assigned-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
    }
    .ds-subadmin-assigned-list {
        margin: 0;
        padding-left: 1.1rem;
        font-size: 0.92rem;
    }
    .ds-subadmin-log-detail {
        font-size: 0.88rem;
        max-width: 28rem;
    }
</style>
@endsection