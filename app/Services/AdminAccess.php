<?php

namespace App\Services;

use App\Models\AdminUserPermission;
use App\Models\User;
use App\Support\AdminPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized Sub-Admin / Main Admin authorization helpers.
 * Main Admin bypasses permission checks; Sub-Admin is deny-by-default.
 */
class AdminAccess
{
    /** @var array<int, list<string>> */
    private array $permissionCache = [];

    /** @var array<string, bool> */
    private array $schemaCache = [];

    public function isMainAdmin(?User $user): bool
    {
        if ($user === null || $user->is_admin !== true) {
            return false;
        }

        $role = (string) ($user->admin_role ?? User::ADMIN_ROLE_MAIN);

        return $role === User::ADMIN_ROLE_MAIN || $role === '';
    }

    public function isSubAdmin(?User $user): bool
    {
        return $user !== null
            && $user->is_admin === true
            && (string) ($user->admin_role ?? '') === User::ADMIN_ROLE_SUB;
    }

    public function isActiveAdmin(?User $user): bool
    {
        if ($user === null || $user->is_admin !== true) {
            return false;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return false;
        }

        if (! $this->usersHasColumn('is_active')) {
            return true;
        }

        return $user->is_active !== false;
    }

    private function usersHasColumn(string $column): bool
    {
        $key = 'users.'.$column;
        if (! array_key_exists($key, $this->schemaCache)) {
            $this->schemaCache[$key] = Schema::hasColumn('users', $column);
        }

        return $this->schemaCache[$key];
    }

    private function hasPermissionsTable(): bool
    {
        if (! array_key_exists('admin_user_permissions', $this->schemaCache)) {
            $this->schemaCache['admin_user_permissions'] = Schema::hasTable('admin_user_permissions');
        }

        return $this->schemaCache['admin_user_permissions'];
    }

    private function hasSessionsTable(): bool
    {
        if (! array_key_exists('sessions', $this->schemaCache)) {
            $this->schemaCache['sessions'] = Schema::hasTable('sessions');
        }

        return $this->schemaCache['sessions'];
    }

    public function can(?User $user, string $permission): bool
    {
        if (! $this->isActiveAdmin($user)) {
            return false;
        }

        if ($this->isMainAdmin($user)) {
            return true;
        }

        if (! AdminPermissionRegistry::isDelegable($permission)) {
            return false;
        }

        return in_array($permission, $this->permissionsFor($user), true);
    }

    public function canAny(?User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, (string) $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(?User $user): array
    {
        if ($user === null || $user->is_admin !== true) {
            return [];
        }

        if ($this->isMainAdmin($user)) {
            return array_keys(AdminPermissionRegistry::definitions());
        }

        $id = (int) $user->id;
        if (isset($this->permissionCache[$id])) {
            return $this->permissionCache[$id];
        }

        if (! $this->hasPermissionsTable()) {
            return $this->permissionCache[$id] = [];
        }

        $keys = AdminUserPermission::query()
            ->where('user_id', $id)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->map(fn ($key) => (string) $key)
            ->all();

        // Expand legacy action keys (members.create, payments.approve, …) to module keys.
        return $this->permissionCache[$id] = AdminPermissionRegistry::normalizeToModuleKeys($keys);
    }

    public function forgetCache(?User $user = null): void
    {
        if ($user === null) {
            $this->permissionCache = [];

            return;
        }

        unset($this->permissionCache[(int) $user->id]);
    }

    /**
     * Replace permission assignments atomically. Returns previous keys.
     *
     * @param  list<string>  $permissionKeys
     * @return array{previous: list<string>, assigned: list<string>}
     */
    public function syncPermissions(User $user, array $permissionKeys): array
    {
        $previous = $this->permissionsFor($user);
        $assigned = AdminPermissionRegistry::filterDelegableKeys($permissionKeys);

        DB::transaction(function () use ($user, $assigned): void {
            AdminUserPermission::query()->where('user_id', $user->id)->delete();
            $now = now();
            $rows = array_map(static fn (string $key): array => [
                'user_id' => $user->id,
                'permission_key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ], $assigned);
            if ($rows !== []) {
                AdminUserPermission::query()->insert($rows);
            }
        });

        $this->forgetCache($user);

        return [
            'previous' => $previous,
            'assigned' => $this->permissionsFor($user->fresh()),
        ];
    }

    public function allowsRoute(?User $user, ?string $routeName): bool
    {
        if (! $this->isActiveAdmin($user)) {
            return false;
        }

        if ($this->isMainAdmin($user)) {
            return true;
        }

        if (AdminPermissionRegistry::isAlwaysAllowedRoute($routeName)) {
            return true;
        }

        $permission = AdminPermissionRegistry::permissionForRoute($routeName);
        if ($permission === null) {
            return false;
        }

        return $this->can($user, $permission);
    }

    public function firstAuthorizedUrl(?User $user): ?string
    {
        if (! $this->isActiveAdmin($user)) {
            return null;
        }

        if ($this->isMainAdmin($user) || $this->can($user, 'dashboard')) {
            return route('dashboard');
        }

        foreach (AdminPermissionRegistry::homeCandidates() as $candidate) {
            if (! $this->can($user, $candidate['permission'])) {
                continue;
            }
            $params = $candidate['params'] ?? [];

            try {
                return route($candidate['route'], $params);
            } catch (\Throwable) {
                continue;
            }
        }

        return route('admin.access-denied', ['reason' => 'none']);
    }

    public function invalidateSessions(User $user): void
    {
        if (! $this->hasSessionsTable()) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
