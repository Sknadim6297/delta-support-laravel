# Sub-Admin Module Access Fix Report

**Project:** Delta Support  
**Date:** 2026-11-26  
**Scope:** Module-based full access (no ordinary CRUD splits), Member Registration workflow for Sub-Admins, permission UI simplification, autocomplete fix.  
**Constraints:** Local only — no deploy, no financial posting, no production mutation.

---

## Verdict

| Item | Status |
|------|--------|
| Root cause identified | **Verified** |
| Module-level full access | **Implemented and verified** |
| Member registration by Sub-Admin | **Implemented and verified** |
| Legacy permission compatibility | **Implemented and verified** |
| Permission UI simplified | **Implemented and verified** |
| Reserved Main-Admin ops intact | **Implemented and verified** |
| Autocomplete (`admin-mobile`) | **Fixed (`tel`)** |
| Browser extension console noise | **Not app JS** |
| Full suite | **730 passed** |
| Vite build | **Successful** |

---

## 1. Actual root cause

The first Sub-Admin implementation used **split action permissions** (`members.view`, `members.create`, `members.edit`, …).

Typical assignment UI / stored keys often granted only `members.view` (listings). Registration, sponsor AJAX (`check-sponsor-id`), store, update, and status routes required **other** keys, so:

- Sidebar “Member Management” could appear (view key).  
- Opening registration or submitting create/update returned **access denied / redirect**, even though the module looked assigned.

This was a mapping/policy mismatch, not a broken registration controller.

---

## 2. Previous vs corrected behavior

| Before | After |
|--------|--------|
| Separate View/Create/Edit/Status/… checkboxes | **One checkbox per ordinary module** |
| `members.view` ≠ registration/store | `members` covers **all** normal Member Management routes |
| Action keys required for mutations | Module key grants full ordinary workflow |
| Legacy DB rows with action keys | Normalized at runtime to module keys |

**Example:** Assigning **Member Management** now allows directory, registration, sponsor lookup AJAX, store, update, status, payment-controls, impersonate/preview, genealogy, ranks — same ordinary workflow as Main Admin for that module.

Reserved routes remain Main-Admin-only (authorize posting, financial controls, Sub-Admin management, etc.).

---

## 3. Member registration verification

Automated (`SubAdminManagementTest`):

- Sub-Admin with `members` opens registration page (OK).  
- Sponsor check AJAX OK.  
- POST `admin.members.store` creates member and redirects.  
- Update + directory pages OK.  
- Registration mobile input includes `autocomplete="tel"`.

Business rules (validation, sponsor, member ID generation, password setup mail) unchanged.

---

## 4. Module permission mapping (delegable)

| Key | Module | Route coverage (summary) |
|-----|--------|---------------------------|
| `dashboard` | Dashboard | `dashboard` |
| `members` | Member Management | `admin.members.*`, `admin.genealogy.*`, `admin.member-panel`, `admin.ranks.*` |
| `tour_requests` | Tour Requests | `admin.tour-requests.*` |
| `packages` | Package Management | `admin.packages.*` + `admin.purchases.*` (except reserved authorize/plan publish) |
| `payments` | Payment Management | `admin.payments.*` |
| `invoices` | Invoices | `admin.invoices.*` |
| `roi` | ROI Management | ROI plans/schedule/history/real-income/income-test |
| `level_income` | Level Income | history, eligibility, demo, commission/unlock settings, draft/clone only |
| `wallets` | Wallet Management | wallets + member-transfers |
| `withdrawals` | Withdrawals | `admin.withdrawals.*` |
| `rewards` | Rewards | `admin.rewards.*` |
| `reports` | Reports | `admin.catalog-reports.*` |
| `support` | Support Tickets | `admin.support.*` |
| `settings` | Settings | general/branding/security (not financial) |

### Reserved (unchanged)

`packages.authorize_accrual`, `packages.plan_publish`, `level_income.publish|authorize|revoke|grandfather`, `settings.financial`, `sub_admins.manage`, `legacy.*`

Exact reserved routes match **before** module wildcards.

---

## 5. Missing / fixed mappings

- Registration + AJAX + store/update/status under `members`.  
- Payment approve/reject under `payments` (module).  
- Withdrawal approve/reject/complete under `withdrawals`.  
- Package purchases under `packages`.  
- Legacy aliases (`members.create` → `members`, etc.) via `normalizeToModuleKeys()`.

---

## 6. Changed files

| File | Change |
|------|--------|
| `app/Support/AdminPermissionRegistry.php` | Module-level registry + legacy aliases |
| `app/Services/AdminAccess.php` | Normalize stored keys to modules when loading |
| `resources/views/layouts/panel/sidebar-admin.blade.php` | Module-key checks |
| `resources/views/admin/sub-admins/form.blade.php` | One checkbox per module UI |
| `resources/views/admin/members/member_registration.blade.php` | `autocomplete="tel"` |
| `resources/views/admin/payments/show.blade.php` | `@can('payments')` |
| `app/Http/Controllers/Admin/WithdrawalReviewController.php` | Module `withdrawals` gates |
| `app/Providers/AppServiceProvider.php` | Header balance uses `wallets` |
| `tests/Feature/SubAdminManagementTest.php` | Module + registration + legacy tests |

No new migrations (compatibility is runtime normalization).

---

## 7. Database compatibility

Existing `admin_user_permissions` rows with legacy keys (e.g. `members.view`) still work: `AdminAccess::permissionsFor()` expands them to module keys.

Saving permissions from the new UI stores **module keys only** and replaces prior rows atomically.

No privilege expansion migration was run blindly: accounts that only had `members.view` now effectively get full `members` module (intentional per client module policy). Accounts with zero member keys remain blocked.

---

## 8. Browser console investigation

| Message | Origin | Action |
|---------|--------|--------|
| `[DOM] Input elements should have autocomplete attributes (suggested: "username")` on `#admin-mobile` | App registration form; field is a **phone number**, not a login username | Set `autocomplete="tel"` (+ `inputmode="numeric"`) |
| `Uncaught (in promise) Error: A listener indicated an asynchronous response…` | Typical **browser extension** messaging (not Delta Support app JS) | No app workaround; ignore unless reproduced with extensions disabled |

---

## 9. Security verification

- Unassigned modules → redirect to Access Denied page.  
- Reserved authorize / financial / Sub-Admin routes → denied for Sub-Admins.  
- Main Admin unrestricted.  
- Existing financial validations still apply to delegated finance modules.  
- No global auth bypass.

---

## 10. Test results

```
SubAdminManagementTest — 11 passed
Full suite — 730 passed (7087 assertions)
```

---

## 11. Vite build results

```
vite build — success (~21s)
```

---

## 12. Remaining issues

- Dashboard quick links/widgets are not fully permission-filtered (unauthorized clicks still hit Access Denied). Optional polish.  
- Re-assign Sub-Admin permissions in Admin UI once to persist clean module keys (optional; legacy keys already work).

---

## 13. Production deployment instructions (when approved)

1. Deploy code (no required migration for this fix).  
2. `php artisan view:clear`  
3. `npm run build`  
4. Smoke: Main Admin → edit Sub-Admin → assign **Member Management** only → Sub-Admin registers a test member successfully.  
5. Confirm unauthorized finance URL shows Access Denied message (not raw 403).  

Do not authorize financial posting as part of this deploy.
