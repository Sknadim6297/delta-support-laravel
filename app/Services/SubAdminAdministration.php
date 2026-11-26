<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\AdminUserPermission;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\AdminPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubAdminAdministration
{
    public function __construct(private readonly AdminAccess $access)
    {
    }

    /**
     * @param  array{
     *     name: string,
     *     login_id: string,
     *     email?: string|null,
     *     password: string,
     *     is_active?: bool,
     *     permissions?: list<string>
     * }  $payload
     */
    public function create(User $actor, array $payload): User
    {
        $this->assertMainAdmin($actor);

        $loginId = $this->normalizeLoginId($payload['login_id']);
        $email = $this->resolveEmail($payload['email'] ?? null, $loginId);
        $permissions = AdminPermissionRegistry::filterDelegableKeys($payload['permissions'] ?? []);

        return DB::transaction(function () use ($actor, $payload, $loginId, $email, $permissions): User {
            $this->assertUniqueIdentifiers($loginId, $email);

            $subAdmin = User::query()->create([
                'name' => trim($payload['name']),
                'email' => $email,
                'login_id' => $loginId,
                'password' => $payload['password'],
                'is_admin' => true,
                'admin_role' => User::ADMIN_ROLE_SUB,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'must_change_password' => false,
                'created_by' => $actor->id,
            ]);

            $sync = $this->access->syncPermissions($subAdmin, $permissions);
            $this->log($actor, 'sub_admin_created', $subAdmin, [
                'permissions' => $sync['assigned'],
                'is_active' => $subAdmin->is_active,
            ]);

            return $subAdmin->fresh(['adminPermissions']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     login_id: string,
     *     email?: string|null,
     *     is_active?: bool,
     *     permissions?: list<string>
     * }  $payload
     */
    public function update(User $actor, User $subAdmin, array $payload): User
    {
        $this->assertMainAdmin($actor);
        $this->assertManagedSubAdmin($subAdmin);

        $loginId = $this->normalizeLoginId($payload['login_id']);
        $email = $this->resolveEmail($payload['email'] ?? null, $loginId, $subAdmin);
        $permissions = AdminPermissionRegistry::filterDelegableKeys($payload['permissions'] ?? []);

        return DB::transaction(function () use ($actor, $subAdmin, $payload, $loginId, $email, $permissions): User {
            $this->assertUniqueIdentifiers($loginId, $email, $subAdmin->id);

            $before = [
                'name' => $subAdmin->name,
                'login_id' => $subAdmin->login_id,
                'email' => $subAdmin->email,
                'is_active' => $subAdmin->is_active,
                'permissions' => $this->access->permissionsFor($subAdmin),
            ];

            $subAdmin->fill([
                'name' => trim($payload['name']),
                'email' => $email,
                'login_id' => $loginId,
                'is_active' => (bool) ($payload['is_active'] ?? $subAdmin->is_active),
            ]);
            $subAdmin->save();

            $sync = $this->access->syncPermissions($subAdmin, $permissions);

            if (! $subAdmin->is_active) {
                $this->access->invalidateSessions($subAdmin);
            }

            $this->log($actor, 'sub_admin_updated', $subAdmin, [
                'before' => $before,
                'after' => [
                    'name' => $subAdmin->name,
                    'login_id' => $subAdmin->login_id,
                    'email' => $subAdmin->email,
                    'is_active' => $subAdmin->is_active,
                    'permissions' => $sync['assigned'],
                ],
            ]);

            return $subAdmin->fresh(['adminPermissions']);
        });
    }

    public function resetPassword(User $actor, User $subAdmin, string $password): void
    {
        $this->assertMainAdmin($actor);
        $this->assertManagedSubAdmin($subAdmin);

        if (! StrongPassword::accepts($password)) {
            throw ValidationException::withMessages([
                'password' => StrongPassword::MESSAGE,
            ]);
        }

        DB::transaction(function () use ($actor, $subAdmin, $password): void {
            $subAdmin->forceFill([
                'password' => $password,
                'remember_token' => null,
            ])->save();
            $this->access->invalidateSessions($subAdmin);
            $this->log($actor, 'sub_admin_password_reset', $subAdmin, []);
        });
    }

    public function setActive(User $actor, User $subAdmin, bool $active): User
    {
        $this->assertMainAdmin($actor);
        $this->assertManagedSubAdmin($subAdmin);

        return DB::transaction(function () use ($actor, $subAdmin, $active): User {
            $subAdmin->forceFill(['is_active' => $active])->save();
            if (! $active) {
                $this->access->invalidateSessions($subAdmin);
            }
            $this->log($actor, $active ? 'sub_admin_activated' : 'sub_admin_deactivated', $subAdmin, [
                'is_active' => $active,
            ]);

            return $subAdmin->fresh();
        });
    }

    /**
     * Soft-delete a Sub-Admin: revoke access, clear permissions/sessions, preserve history.
     */
    public function delete(User $actor, User $subAdmin): void
    {
        $this->assertMainAdmin($actor);
        $this->assertManagedSubAdmin($subAdmin);

        if ((int) $actor->id === (int) $subAdmin->id) {
            abort(403, 'You cannot delete your own account.');
        }

        DB::transaction(function () use ($actor, $subAdmin): void {
            $snapshot = [
                'id' => $subAdmin->id,
                'name' => $subAdmin->name,
                'login_id' => $subAdmin->login_id,
                'email' => $subAdmin->email,
                'permissions' => $this->access->permissionsFor($subAdmin),
            ];

            AdminUserPermission::query()->where('user_id', $subAdmin->id)->delete();
            $this->access->forgetCache($subAdmin);

            $subAdmin->forceFill([
                'is_active' => false,
                'remember_token' => null,
                'login_id' => $this->retireLoginId((string) $subAdmin->login_id, (int) $subAdmin->id),
                'email' => $this->retireEmail((string) $subAdmin->email, (int) $subAdmin->id),
            ])->save();

            $this->access->invalidateSessions($subAdmin);
            $subAdmin->delete();

            $this->log($actor, 'sub_admin_deleted', $subAdmin, [
                'login_id' => $snapshot['login_id'],
                'name' => $snapshot['name'],
                'deleted' => $snapshot,
                'deleted_by' => $actor->id,
                'deleted_at' => now()->toIso8601String(),
            ]);
        });
    }

    private function assertMainAdmin(User $actor): void
    {
        if (! $this->access->isMainAdmin($actor) || ! $this->access->isActiveAdmin($actor)) {
            abort(403);
        }
    }

    private function assertManagedSubAdmin(User $subAdmin): void
    {
        if (method_exists($subAdmin, 'trashed') && $subAdmin->trashed()) {
            throw ValidationException::withMessages([
                'sub_admin' => 'That Sub-Admin account has already been deleted.',
            ]);
        }

        if (! $subAdmin->is_admin || (string) $subAdmin->admin_role !== User::ADMIN_ROLE_SUB) {
            throw ValidationException::withMessages([
                'sub_admin' => 'Only Sub-Admin accounts can be managed here.',
            ]);
        }

        if ($this->access->isMainAdmin($subAdmin)) {
            abort(403, 'The Main Admin account cannot be modified through Sub-Admin Management.');
        }
    }

    private function retireLoginId(string $loginId, int $id): string
    {
        $suffix = '.DEL.'.$id.'.'.time();
        $base = strtoupper(substr(preg_replace('/[^A-Z0-9._-]/i', '', $loginId) ?: 'SUB', 0, max(1, 64 - strlen($suffix))));

        return $base.$suffix;
    }

    private function retireEmail(string $email, int $id): string
    {
        return 'deleted+'.$id.'.'.time().'@deleted.local';
    }

    private function normalizeLoginId(string $loginId): string
    {
        $loginId = strtoupper(trim($loginId));
        if ($loginId === '' || ! preg_match('/^[A-Z0-9._-]{3,64}$/', $loginId)) {
            throw ValidationException::withMessages([
                'login_id' => 'Login ID must be 3–64 characters using letters, numbers, dot, underscore or hyphen.',
            ]);
        }

        if ($loginId === MemberRegistrationService::ADMIN_SPONSOR_ID) {
            throw ValidationException::withMessages([
                'login_id' => 'That Login ID is reserved.',
            ]);
        }

        return $loginId;
    }

    private function resolveEmail(?string $email, string $loginId, ?User $existing = null): string
    {
        $email = trim((string) $email);
        if ($email === '') {
            if ($existing && is_string($existing->email) && $existing->email !== '') {
                return $existing->email;
            }

            return strtolower($loginId).'@subadmin.local';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => 'Enter a valid email address or leave it blank.',
            ]);
        }

        return strtolower($email);
    }

    private function assertUniqueIdentifiers(string $loginId, string $email, ?int $ignoreId = null): void
    {
        $loginQuery = User::query()->whereRaw('LOWER(login_id) = ?', [strtolower($loginId)]);
        $emailQuery = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)]);
        if ($ignoreId !== null) {
            $loginQuery->where('id', '!=', $ignoreId);
            $emailQuery->where('id', '!=', $ignoreId);
        }

        if ($loginQuery->exists()) {
            throw ValidationException::withMessages([
                'login_id' => 'That Login ID is already in use.',
            ]);
        }

        if ($emailQuery->exists()) {
            throw ValidationException::withMessages([
                'email' => 'That email is already in use.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function log(User $actor, string $action, User $subject, array $filters): void
    {
        AdminActivityLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject' => 'sub_admin:'.$subject->id,
            'format' => null,
            'scope' => 'admin',
            'filters' => array_merge([
                'login_id' => $subject->login_id,
                'name' => $subject->name,
            ], $filters),
            'row_count' => 1,
        ]);
    }
}
