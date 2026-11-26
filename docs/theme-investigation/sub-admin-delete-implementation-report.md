# Sub-Admin Delete — Implementation Report

**Date:** 2026-11-26  
**Scope:** Local only — secure Delete Sub-Admin for Main Admin, soft-delete with session revoke and audit.  
**Baseline:** Prior suite 730 tests → **734 passed (7123 assertions)** after this change.

---

## Summary

Main Admin can delete Sub-Admin accounts from the listing and detail pages. Deletion uses Laravel soft deletes on `users`, clears permission rows, invalidates sessions, retires unique identifiers for reuse, and writes an audit log. Members, wallets, payments, and other business history are untouched. Sub-Admins cannot delete any administrator.

---

## Behavior

| Action | Result |
|--------|--------|
| Main Admin → Delete (confirmed) | Soft-delete Sub-Admin; listing removes row; flash success |
| Cancel confirmation | No request; account unchanged |
| Deleted Sub-Admin login | Rejected (Eloquent excludes soft-deleted users) |
| Existing sessions | Rows removed from `sessions`; account inactive |
| Sub-Admin → DELETE URL | Access Denied (reserved `sub_admins.*`) |
| DELETE Main Admin via route | 404 (`assertSubAdminModel`) |
| Self-delete | Blocked in controller + service |

Confirmation dialog (Panel V2 / `window.confirm`) includes **name** and **Login ID**.

---

## Data & financial safety

- **Soft delete:** `users.deleted_at` — row kept for accountability and FKs (`created_by`, payment/audit `user_id`, etc.).
- **Permissions:** `admin_user_permissions` rows for that user are deleted (no orphans; soft delete would not fire `cascadeOnDelete`).
- **Sessions:** `AdminAccess::invalidateSessions()` + `remember_token` cleared + `is_active = false`.
- **Unique IDs:** `login_id` / `email` retired (`…DEL.{id}.{time}` / `deleted+{id}.{time}@deleted.local`) so the same Login ID can be re-created without colliding with the soft-deleted row.
- **Business data:** No cascades into members, packages, payments, withdrawals, wallets, or finance tables.
- **Audit:** `AdminActivityLog` action `sub_admin_deleted` with actor id, subject `sub_admin:{id}`, original name/login_id, permission snapshot, timestamp.

---

## Security

- Backend: `assertMainAdmin` on `destroy`; reserved route group `admin.sub-admins.*` denied to Sub-Admins via `EnsureAdminPermission`.
- CSRF + `DELETE` method on form (`@csrf` + `@method('DELETE')`).
- Main Admin / non-sub accounts: 404 from `assertSubAdminModel`.
- Soft-deleted / trashed accounts treated inactive in `AdminAccess::isActiveAdmin`.
- Module-based permission system unchanged; no new privilege paths.

---

## Migration

| File | Purpose |
|------|---------|
| `database/migrations/2026_11_26_130000_add_soft_deletes_to_users_table.php` | Adds nullable `users.deleted_at` |

Run locally:

```bash
php artisan migrate
```

No destructive schema changes. Existing Sub-Admin credentials and module permissions are preserved until explicitly deleted.

---

## Changed files

| Path | Change |
|------|--------|
| `database/migrations/2026_11_26_130000_add_soft_deletes_to_users_table.php` | Soft deletes column |
| `app/Models/User.php` | `SoftDeletes` trait |
| `app/Services/SubAdminAdministration.php` | `delete()`, identifier retirement, trashed guard |
| `app/Services/AdminAccess.php` | Trashed ⇒ inactive |
| `app/Http/Controllers/Admin/SubAdminController.php` | `destroy()` |
| `routes/web.php` | `DELETE admin/sub-admins/{subAdmin}` → `destroy` |
| `resources/views/admin/sub-admins/index.blade.php` | Delete action + confirm + error alert |
| `resources/views/admin/sub-admins/show.blade.php` | Delete action + confirm + deleted log label |
| `tests/Feature/SubAdminManagementTest.php` | Four delete / security / reuse tests |
| `docs/theme-investigation/sub-admin-delete-implementation-report.md` | This report |

---

## Tests added

1. `test_main_admin_can_delete_sub_admin_and_access_is_revoked` — soft delete, listing empty, sessions/permissions cleared, login blocked, audit row, route 404, member count unchanged  
2. `test_sub_admin_cannot_delete_administrators` — Access Denied for peer and self  
3. `test_main_admin_account_cannot_be_deleted_via_sub_admin_destroy` — 404  
4. `test_deleted_login_id_can_be_reused_for_new_sub_admin` — Login ID reuse after soft delete  

Existing Sub-Admin module permission tests remain green.

---

## Test & Vite results

| Check | Result |
|-------|--------|
| `php artisan test` | **734 passed (7123 assertions)**, ~152s |
| `npm run build` | **OK** (Vite 7.3.7, ~5.2s) |
| Logs | `storage/logs/subadmin-delete-test-run.txt`, `storage/logs/subadmin-delete-junit.xml`, `storage/logs/subadmin-delete-vite-build.txt` |

Baseline was 730 tests; +4 delete-related tests → 734.

---

## Remaining issues / notes

- No Trash / Restore UI (by design). Soft-deleted rows can be inspected via DB / `User::withTrashed()` if ever needed operationally.
- Confirmation uses the same `window.confirm` pattern as Deactivate / other Panel V2 admin deletes (not a custom modal component).
- Dashboard quick links remain out of scope for this change.

---

## Production deployment instructions (do not run here)

1. Deploy code including the soft-deletes migration.  
2. Run `php artisan migrate` (adds `users.deleted_at` only).  
3. Clear caches if used: `php artisan view:clear` / `config:clear` as usual.  
4. Smoke-test as Main Admin: Sub-Admin listing → Delete → confirm → success flash; attempt login as deleted Login ID.  
5. Confirm Sub-Admin cannot open Sub-Admin Management / DELETE URL.  
6. Do **not** authorize financial posting, run financial jobs, or modify production financial records as part of this deploy.

**This work was implemented and verified locally only.**
