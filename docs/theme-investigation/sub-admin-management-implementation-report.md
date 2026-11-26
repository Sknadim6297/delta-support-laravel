# Sub-Admin Management & Dynamic Access Control — Implementation Report

**Project:** Delta Support (`C:\xampp\htdocs\delta_support`)  
**Date:** 2026-11-26  
**Scope:** Main Admin–managed Sub-Admin accounts with checkbox menu/action permissions, shared admin login, Panel V2 UI, deny-by-default backend authorization.  
**Constraints:** Local only — no deploy, no git commit/push, no production account changes, no financial posting authorization, no historical ledger mutation.

---

## Verdict

| Item | Classification |
|------|----------------|
| Architecture audit | **Implemented and verified** |
| Schema + Main Admin preservation | **Implemented and verified** |
| Permission registry (single source of truth) | **Implemented and verified** |
| Sub-Admin CRUD / password reset / activate | **Implemented and verified** |
| Shared login + Login ID auth | **Implemented and verified** |
| Sidebar filtering | **Implemented and verified** (manual + automated) |
| Backend deny-by-default route auth | **Implemented and verified** |
| Reserved Main-Admin-only operations | **Implemented and verified** |
| Financial regression (suite) | **Implemented and verified** |
| Panel V2 UI | **Implemented and verified** |
| Full automated suite | **730 passed** |
| Vite production build | **Successful** |
| Manual E2E + screenshots | **Implemented and verified** (local) |
| Dashboard widget/quick-link filtering | **Pending** (backend still enforces; some dashboard cards remain visible) |

---

## 1. Existing architecture audit

| Area | Finding |
|------|---------|
| Guard | Single `web` session guard → `App\Models\User` |
| Admin gate | Middleware `admin` (`EnsureUserIsAdmin`) — previously only `is_admin === true` |
| Login | Shared `PublicMemberAuthController@login` on `/admin/login` + `/login` |
| Roles | None — binary `is_admin` only; `SuperAdminProvisioner` assumed one admin |
| Permissions / Gates / Policies | None for admin RBAC |
| Sidebar | Hard-coded `resources/views/layouts/panel/sidebar-admin.blade.php` (Panel V2) |
| Audit | `admin_activity_logs` via `AdminActivityLog` |
| Financial safety | `legacy.financial`, Financial Controls, domain authorize-posting — all previously reachable by any admin |
| Theme | Panel V2 (`admin.layouts.app`, `x-ui.*`, Vite panel assets) |

**Decision:** Extend `users` + add `admin_user_permissions`; reuse `web` guard and Panel V2; add centralized registry + middleware — do not introduce Spatie or a second app.

---

## 2. Final architecture (why)

1. **`admin_role` = `main` | `sub`** on `users` — explicit distinction without hardcoded user IDs.  
2. **`login_id`** — unique Sub-Admin login on the existing login form.  
3. **`admin_user_permissions`** — normalized permission keys with unique `(user_id, permission_key)`.  
4. **`AdminPermissionRegistry`** — sole map of permissions ↔ routes ↔ UI modules.  
5. **`AdminAccess` singleton** — `can()`, route allow, sync, session invalidation; Main Admin bypasses checks.  
6. **`admin.permission` middleware** on the authenticated admin route group — deny-by-default for Sub-Admins; unmapped routes denied.  
7. **Reserved permissions** never appear in assignment UI / Select All.

Existing Main Admin accounts are migrated to `admin_role=main` and retain full access. Factory defaults Main Admin so the 700+ existing admin tests remain valid.

---

## 3. Implementation summary

- Sub-Admin Management under **Account → Settings** (Main Admin only).  
- Create / edit account + module/action checkboxes (Select All, module select, selected count).  
- Password reset (StrongPassword) invalidates DB sessions + remember token.  
- Activate / deactivate blocks login and middleware access; deactivation clears sessions.  
- Login supports: Main Admin email / sponsor ID, Sub-Admin Login ID, existing throttling/CSRF/session regenerate.  
- Post-login: Dashboard if permitted, else first authorized route, else Access Not Assigned.  
- Sidebar shows only assigned menus; empty parent groups hidden.  
- Payment approve/reject and withdrawal action buttons gated with `@can` / permission flags.

---

## 4. Changed / new files

### New

| File |
|------|
| `database/migrations/2026_11_26_110000_add_sub_admin_management.php` |
| `app/Models/AdminUserPermission.php` |
| `app/Support/AdminPermissionRegistry.php` |
| `app/Services/AdminAccess.php` |
| `app/Services/SubAdminAdministration.php` |
| `app/Http/Controllers/Admin/SubAdminController.php` |
| `app/Http/Middleware/EnsureAdminPermission.php` |
| `resources/views/admin/sub-admins/index.blade.php` |
| `resources/views/admin/sub-admins/form.blade.php` |
| `resources/views/admin/sub-admins/show.blade.php` |
| `resources/views/admin/sub-admins/reset-password.blade.php` |
| `resources/views/admin/access-denied.blade.php` |
| `tests/Feature/SubAdminManagementTest.php` |
| `docs/theme-investigation/sub-admin-management-implementation-report.md` |
| `docs/theme-investigation/screenshots/sub-admin-*.png` |

### Modified

| File |
|------|
| `app/Models/User.php` |
| `app/Http/Middleware/EnsureUserIsAdmin.php` |
| `app/Http/Controllers/PublicMemberAuthController.php` |
| `app/Http/Controllers/Admin/WithdrawalReviewController.php` |
| `app/Providers/AppServiceProvider.php` |
| `app/Services/SuperAdminProvisioner.php` |
| `bootstrap/app.php` |
| `routes/web.php` |
| `database/factories/UserFactory.php` |
| `resources/views/layouts/panel/sidebar-admin.blade.php` |
| `resources/views/admin/payments/show.blade.php` |

---

## 5. Database migrations & production safety

**Migration:** `2026_11_26_110000_add_sub_admin_management` (local batch **48** — Ran)

**Additive `users` columns:** `login_id` (nullable unique), `admin_role`, `is_active` (default true), `last_login_at`, `created_by` (FK nullOnDelete).

**New table:** `admin_user_permissions` (`user_id`, `permission_key`, unique pair, index on key).

**Data safety:** Existing `is_admin=true` rows updated to `admin_role=main`, `is_active=true`. No drops, no financial table changes, no password rewrites for existing accounts.

**Recommended prod deploy order (when approved):** backup DB → deploy code → `php artisan migrate --force` → smoke Main Admin login → create one Sub-Admin in staging first.

---

## 6. Permission matrix (delegable vs reserved)

### Delegable (assignable checkboxes)

| Group | Keys (summary) |
|-------|----------------|
| Overview | `dashboard` |
| Members | `members.view`, `.create`, `.edit`, `.status`, `.payment_controls`, `.impersonate`, `ranks.manage` |
| Commerce | `tour_requests.*`, `packages.view/create/edit/manage_status`, `purchases.view/create`, `payments.view/approve/reject/settings`, `invoices.view` |
| Finance | `roi.view`, `roi.income_test`, `level_income.view`, `level_income.settings`, `wallets.view`, `transfers.view/reverse/settings`, `withdrawals.view/approve/reject/complete/settings` |
| Insights | `rewards.view/manage`, `reports.view/export` |
| Support | `support.view/manage` |
| Account | `settings.general`, `settings.branding`, `settings.security` |

### Reserved (Main Admin only — not in Select All)

- `packages.authorize_accrual`, `packages.plan_publish`  
- `level_income.publish`, `level_income.authorize`, `level_income.revoke`, `level_income.grandfather`  
- `settings.financial`  
- `sub_admins.manage`  
- `legacy.investments`, `legacy.activation_wallet`, `legacy.reports`

### Always allowed (any active admin)

Logout, change-password, access-denied, profile update/password, security page, impersonation stop, coming-soon.

---

## 7–8. Authentication flows

### Main Admin

1. Email / sponsor ID `ST666666` + password against Main Admin row.  
2. Active check → session regenerate → `last_login_at` → audit `admin_login`.  
3. Intended redirect / Dashboard (full access).

### Sub-Admin

1. Login ID on shared form → active Sub-Admin + password hash.  
2. Session regenerate → permissions loaded from DB each request (no stale session permission blob).  
3. Redirect to first authorized URL (or Access Not Assigned).  
4. Inactive / deactivated → login rejected; middleware returns 403 on existing session.

---

## 9. Sidebar filtering

`sidebar-admin.blade.php` uses `AdminAccess::can` / `canAny`. Parent sections render only when at least one child is allowed. Sub-Admin Management + Financial Controls only for Main Admin (`can` reserved keys).

---

## 10. Backend route authorization

Admin group middleware stack:

`auth` → `admin` (is_admin + active) → **`admin.permission`** → `admin.password` → `impersonation.block`

Sub-Admin: always-allowed routes pass; else registry permission required; **null mapping = deny**. Main Admin unrestricted.

---

## 11. Main-Admin-only protected operations

Sub-Admin Management; Financial Controls; Level Income publish/authorize/revoke/grandfather; package accrual authorize / plan publish; legacy investment/activation/report modules.

---

## 12. Security audit findings

| Finding | Status |
|---------|--------|
| Privilege escalation via Sub-Admin create | Blocked (403) |
| Reserved permission assignment | Validation reject |
| Duplicate Login ID | Rejected |
| Password plaintext storage | Hashed via Eloquent cast + StrongPassword |
| Session after deactivate / password reset | Sessions deleted |
| URL bypass to wallets / financial | 403 verified manually |
| Select All includes reserved | No |
| Main Admin accidental demotion via module | Module only manages `admin_role=sub` |

---

## 13. Financial regression verification

Full suite includes ROI, Level Income, grandfathering, combined unlock, wallets, withdrawals, Tour Only BV, payment controls — **730 passed**. No financial posting authorized; no Associate Commission enablement; no historical recalculation.

---

## 14. UI screenshots (local)

| Screenshot | Path |
|------------|------|
| Sub-Admin list | `docs/theme-investigation/screenshots/sub-admin-list.png` |
| Create + permissions | `docs/theme-investigation/screenshots/sub-admin-create-permissions.png` |
| Limited Sub-Admin sidebar | `docs/theme-investigation/screenshots/sub-admin-limited-sidebar.png` |
| Unauthorized financial URL | `docs/theme-investigation/screenshots/sub-admin-forbidden-financial.png` |

---

## 15. Automated test results

```
Tests\Feature\SubAdminManagementTest — 11 passed
Full suite — 730 passed (7075 assertions)
Duration ~183s
```

Logs: `storage/logs/subadmin-full-test-run.txt`, `storage/logs/subadmin-full-junit.xml`

---

## 16. Vite build results

```
vite build — success (~7.3s)
```

---

## 17. Manual verification results (local non-production)

| Step | Result |
|------|--------|
| Main Admin login | OK |
| Open Sub-Admin Management | OK |
| Create Sub-Admin `E2ESUB01` with Dashboard + Members + Support | OK (script + UI) |
| Sub-Admin login | OK |
| Sidebar limited to assigned modules | OK (screenshot) |
| Assigned pages work | OK (dashboard, members) |
| Unauthorized `/admin/wallets` | **403** |
| Unauthorized `/admin/settings/financial` | **403** |
| Deactivate blocks access | OK (script + feature test) |
| Main Admin menus unchanged | OK |

Local test Login ID used: `E2ESUB01` (password not recorded in this report).

---

## 18. Production deployment commands (when approved)

```bash
php artisan down
php artisan migrate --force
php artisan view:clear
php artisan config:cache
php artisan route:cache
npm run build
php artisan up
```

Smoke: Main Admin login → Sub-Admin Management visible → create inactive Sub-Admin first if desired.

---

## 19. Rollback considerations

```bash
php artisan migrate:rollback --step=1
```

Rolls back `admin_user_permissions` and added `users` columns. Deploy previous code revision with rollback. Existing Main Admin passwords unchanged by this feature.

---

## 20. Remaining limitations / pending work

1. **Dashboard cards / quick links** still show commerce/rewards shortcuts to Sub-Admins; clicking unauthorized targets returns 403. Optional follow-up: gate dashboard widgets the same way as sidebar.  
2. **Not every mutation button** across all admin blades is `@can`-wrapped; payment approve/reject and withdrawal actions are. Backend middleware remains authoritative.  
3. **Synthetic email** `{login_id}@subadmin.local` when email omitted — required by unique email column.  
4. **Do not deploy** until ops review; keep Sub-Admin Management Main-Admin-only in production.

---

## Definition of Done checklist

- [x] Main Admin creates/manages Sub-Admins  
- [x] Login ID + password auth on existing login page  
- [x] Checkbox menu/action permissions  
- [x] Sub-Admin sees assigned menus only  
- [x] Unauthorized backend access blocked  
- [x] Permission updates + deactivation work  
- [x] Main Admin access unchanged  
- [x] Financial controls intact (suite)  
- [x] Panel V2 preserved  
- [x] Full regression tests pass (730)  
- [x] Vite build succeeds  
- [x] Documentation complete  
