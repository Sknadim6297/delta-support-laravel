@extends('admin.layouts.app')
@php
    $isEdit = $mode === 'edit';
    $pageTitle = $isEdit
        ? (request()->routeIs('admin.sub-admins.permissions') ? 'Sub-Admin Permissions' : 'Edit Sub-Admin')
        : 'Create Sub-Admin';
    $selected = array_values(array_unique(old(
        'permissions',
        \App\Support\AdminPermissionRegistry::normalizeToModuleKeys($selectedPermissions)
    )));
    $selectedLookup = array_fill_keys($selected, true);
@endphp
@section('title', $pageTitle)
@section('page-title', $pageTitle)
@section('content')
<div class="ds-catalog ds-subadmin">
    <x-ui.page-header
        kicker="Security"
        :title="$pageTitle"
        lead="Assign modules with one checkbox each. Selecting a module grants its complete normal workflow. Reserved Main Admin operations cannot be delegated."
    >
        <x-slot:actions>
            @if ($isEdit && $subAdmin)
                <x-ui.button :href="route('admin.sub-admins.show', $subAdmin)" variant="secondary">Account details</x-ui.button>
            @else
                <x-ui.button :href="route('admin.sub-admins.index')" variant="secondary">All Sub-Admins</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form
        id="subAdminForm"
        method="POST"
        action="{{ $isEdit ? route('admin.sub-admins.update', $subAdmin) : route('admin.sub-admins.store') }}"
        class="ds-subadmin-form"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-ui.card title="Account details">
            <div class="ds-field-grid">
                <label>
                    <span>Full name <span>*</span></span>
                    <input name="name" value="{{ old('name', $subAdmin->name ?? '') }}" maxlength="120" required autocomplete="name">
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    <span>Login ID <span>*</span></span>
                    <input name="login_id" value="{{ old('login_id', $subAdmin->login_id ?? '') }}" maxlength="64" required autocomplete="username" spellcheck="false" style="text-transform:uppercase;">
                    @error('login_id')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    <span>Email <span class="ds-muted">(optional)</span></span>
                    <input type="email" name="email" value="{{ old('email', $subAdmin->email ?? '') }}" maxlength="255" autocomplete="off">
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>
                @unless ($isEdit)
                    <label>
                        <span>Password <span>*</span></span>
                        <input type="password" name="password" autocomplete="new-password" required>
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Confirm password <span>*</span></span>
                        <input type="password" name="password_confirmation" autocomplete="new-password" required>
                        @error('password_confirmation')<small>{{ $message }}</small>@enderror
                    </label>
                @endunless
            </div>
            @unless ($isEdit)
                <p class="ds-pkg-hint">{{ \App\Rules\StrongPassword::MESSAGE }}</p>
            @endunless
            <label class="ds-pkg-check ds-subadmin-active-check" style="display:flex;gap:8px;align-items:center;margin:12px 0 0;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $subAdmin->is_active ?? true))>
                <span>Account is active (can sign in when permissions are assigned)</span>
            </label>
            @error('is_active')<small>{{ $message }}</small>@enderror
        </x-ui.card>

        <x-ui.card title="Panel permissions" subtitle="One checkbox per module grants its complete normal workflow (listings, forms, AJAX, submissions).">
            <div class="ds-subadmin-perm-toolbar">
                <span class="ds-subadmin-perm-count" id="dsSubadminPermCount">0 selected</span>
                <div class="ds-row-actions">
                    <button class="ds-btn ds-btn-secondary" type="button" id="dsSubadminSelectAll">Select all</button>
                    <button class="ds-btn ds-btn-secondary" type="button" id="dsSubadminDeselectAll">Clear all</button>
                </div>
            </div>
            @error('permissions')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror

            <div class="ds-subadmin-module-grid">
                @foreach ($modules as $module)
                    @php
                        $definition = $module['permissions'][0] ?? null;
                    @endphp
                    @continue(! $definition)
                    <label class="ds-pkg-check ds-subadmin-module-check">
                        <input
                            type="checkbox"
                            class="js-subadmin-perm"
                            name="permissions[]"
                            value="{{ $definition['key'] }}"
                            data-key="{{ $definition['key'] }}"
                            @checked(isset($selectedLookup[$definition['key']]))
                        >
                        <span>
                            <strong>{{ $module['module'] }}</strong>
                            <small class="ds-muted" style="display:block;">{{ $module['group'] }} — full module access</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-ui.card>

        <div class="ds-form-actions">
            <x-ui.button type="submit">{{ $isEdit ? 'Save changes' : 'Create Sub-Admin' }}</x-ui.button>
            <x-ui.button :href="route('admin.sub-admins.index')" variant="secondary">Cancel</x-ui.button>
        </div>
    </form>
</div>

<style>
    .ds-subadmin-perm-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .ds-subadmin-perm-count {
        font-weight: 650;
        font-size: 0.92rem;
    }
    .ds-subadmin-module-grid {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
    }
    .ds-subadmin-module-check {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        margin: 0;
        padding: 0.75rem;
        border: 1px solid var(--ds-border, #e4dccb);
        border-radius: 0.5rem;
        background: var(--ds-surface, #fff);
        font-weight: 500;
    }
</style>
@endsection

@section('scripts')
<script>
(function () {
    const form = document.getElementById('subAdminForm');
    if (!form) return;
    const countEl = document.getElementById('dsSubadminPermCount');
    const boxes = () => Array.from(form.querySelectorAll('.js-subadmin-perm'));
    function updateCount() {
        if (countEl) {
            countEl.textContent = boxes().filter(function (el) { return el.checked; }).length + ' selected';
        }
    }
    boxes().forEach(function (box) {
        box.addEventListener('change', updateCount);
    });
    updateCount();
    document.getElementById('dsSubadminSelectAll')?.addEventListener('click', function () {
        boxes().forEach(function (el) { el.checked = true; });
        updateCount();
    });
    document.getElementById('dsSubadminDeselectAll')?.addEventListener('click', function () {
        boxes().forEach(function (el) { el.checked = false; });
        updateCount();
    });
})();
</script>
@endsection
