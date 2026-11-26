<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AdminAccess;
use App\Services\SubAdminAdministration;
use App\Support\AdminPermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubAdminController extends Controller
{
    public function __construct(
        private readonly SubAdminAdministration $administration,
        private readonly AdminAccess $access,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertMainAdmin($request);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $query = User::query()
            ->where('is_admin', true)
            ->where('admin_role', User::ADMIN_ROLE_SUB)
            ->withCount('adminPermissions')
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('login_id', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.sub-admins.index', [
            'subAdmins' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $this->assertMainAdmin($request);

        return view('admin.sub-admins.form', [
            'subAdmin' => null,
            'selectedPermissions' => [],
            'modules' => AdminPermissionRegistry::modulesForUi(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $data = $this->validatedAccount($request, true);
        $subAdmin = $this->administration->create($request->user(), $data);

        return redirect()
            ->route('admin.sub-admins.show', $subAdmin)
            ->with('status', 'Sub-Admin account created.');
    }

    public function show(Request $request, User $subAdmin): View
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);

        $logs = AdminActivityLog::query()
            ->where(function ($query) use ($subAdmin): void {
                $query->where('subject', 'sub_admin:'.$subAdmin->id)
                    ->orWhere(function ($inner) use ($subAdmin): void {
                        $inner->where('user_id', $subAdmin->id)
                            ->whereIn('action', ['admin_login', 'admin_logout']);
                    });
            })
            ->latest('id')
            ->limit(40)
            ->get();

        return view('admin.sub-admins.show', [
            'subAdmin' => $subAdmin->loadCount('adminPermissions'),
            'permissions' => $this->access->permissionsFor($subAdmin),
            'modules' => AdminPermissionRegistry::modulesForUi(),
            'logs' => $logs,
        ]);
    }

    public function edit(Request $request, User $subAdmin): View
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);

        return view('admin.sub-admins.form', [
            'subAdmin' => $subAdmin,
            'selectedPermissions' => $this->access->permissionsFor($subAdmin),
            'modules' => AdminPermissionRegistry::modulesForUi(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, User $subAdmin): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);
        $data = $this->validatedAccount($request, false);
        $this->administration->update($request->user(), $subAdmin, $data);

        return redirect()
            ->route('admin.sub-admins.show', $subAdmin)
            ->with('status', 'Sub-Admin account updated.');
    }

    public function permissions(Request $request, User $subAdmin): View
    {
        return $this->edit($request, $subAdmin);
    }

    public function resetPasswordForm(Request $request, User $subAdmin): View
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);

        return view('admin.sub-admins.reset-password', [
            'subAdmin' => $subAdmin,
        ]);
    }

    public function resetPassword(Request $request, User $subAdmin): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);

        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $this->administration->resetPassword($request->user(), $subAdmin, $data['password']);

        return redirect()
            ->route('admin.sub-admins.show', $subAdmin)
            ->with('status', 'Password reset. Existing sessions were signed out.');
    }

    public function activate(Request $request, User $subAdmin): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);
        $this->administration->setActive($request->user(), $subAdmin, true);

        return back()->with('status', 'Sub-Admin activated.');
    }

    public function deactivate(Request $request, User $subAdmin): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);
        $this->administration->setActive($request->user(), $subAdmin, false);

        return back()->with('status', 'Sub-Admin deactivated. Active sessions were revoked.');
    }

    public function destroy(Request $request, User $subAdmin): RedirectResponse
    {
        $this->assertMainAdmin($request);
        $this->assertSubAdminModel($subAdmin);

        if ((int) $request->user()->id === (int) $subAdmin->id) {
            return back()->withErrors([
                'sub_admin' => 'You cannot delete your own account.',
            ]);
        }

        $name = (string) $subAdmin->name;
        $loginId = (string) $subAdmin->login_id;
        $this->administration->delete($request->user(), $subAdmin);

        return redirect()
            ->route('admin.sub-admins.index')
            ->with('status', 'Sub-Admin "'.$name.'" ('.$loginId.') deleted. Access revoked and sessions signed out.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAccount(Request $request, bool $requirePassword): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'login_id' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:96'],
        ];

        if ($requirePassword) {
            $rules['password'] = ['required', 'string', 'confirmed', new StrongPassword];
        }

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');
        $data['permissions'] = array_values($data['permissions'] ?? []);

        $invalid = array_diff($data['permissions'], array_keys(AdminPermissionRegistry::definitions()));
        if ($invalid !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'permissions' => 'One or more permission identifiers are invalid.',
            ]);
        }

        $reserved = array_values(array_intersect($data['permissions'], AdminPermissionRegistry::reservedKeys()));
        if ($reserved !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'permissions' => 'Reserved Main Admin permissions cannot be assigned.',
            ]);
        }

        return $data;
    }

    private function assertMainAdmin(Request $request): void
    {
        abort_unless($this->access->isMainAdmin($request->user()), 403);
    }

    private function assertSubAdminModel(User $subAdmin): void
    {
        abort_unless(
            $subAdmin->is_admin === true
                && (string) $subAdmin->admin_role === User::ADMIN_ROLE_SUB
                && ! $subAdmin->trashed(),
            404
        );
    }
}
