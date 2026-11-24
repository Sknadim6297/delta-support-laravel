# Dynamic Level Unlock & Commission Management — Implementation Report

**Mode:** Local implementation only. New business rules are **not** activated for financial posting.  
**Date:** 2026-11-24  
**Stop condition:** Stopped before authorizing / activating proposed unlock bands for `financial:daily`.

---

## 1. Verdict

Versioned Level Income unlock bands and commission percentages are admin-manageable with draft → validate → publish → (optional) authorize posting. Live accrual still uses **hard-coded legacy** unlock/rates until a published version is explicitly `posting_authorized`.

Proposed defaults (active + 0 directs → levels 1–4, …, 4+ directs → 1–20) exist only as draft `proposed_activation_unlock_v1`.

---

## 2. Database structure (additive)

Migration: `database/migrations/2026_11_24_130000_create_level_income_rule_tables.php`

| Table | Purpose |
| --- | --- |
| `level_income_rule_versions` | Version metadata: status, `posting_authorized`, applicability, effective_from |
| `level_income_unlock_bands` | Contiguous level ranges + min qualifying directs + personal activation flag |
| `level_income_commission_rates` | Per-level percentage (4 dp) for levels 1–20 |
| `level_income_rule_audits` | Admin audit trail (before/after JSON) |

**Seeded rows**

- `legacy_live_v1` — **published**, `posting_authorized=false` (mirrors current hard-coded ladder/rates)
- `proposed_activation_unlock_v1` — **draft**, not live

Production migrations were **not** executed from this workspace beyond local. Deploy separately after approval.

---

## 3. Services & engine wiring

| Component | Role |
| --- | --- |
| `LevelIncomeRuleResolver` | Resolves rates/unlock; DB chart only if published + `posting_authorized` |
| `LevelIncomeRuleAdministration` | Draft CRUD, validation, publish, authorize/revoke, impact preview, audits |
| `ReferralLevelEligibility` | Uses resolver; adds `personally_activated` + `next_unlock`; `levelsForCount()` stays legacy until authorized |
| `CommissionPlan::rateChart()` / `rateForLevel()` | Runtime rates via resolver |
| `PackageIncomePolicy::levelAmount()` | Uses resolver rates |
| `PackageIncomeAccrual` | Records rate from plan resolver; unlock still via `forMember()` |

**Preserved:** upline traversal, recipient eligibility, package income definitions, decimal math, accrual windows, idempotency, wallet/ledger behavior, financial controls, emergency stop, scheduled jobs. Associate Commission posting remains disabled. No parallel posting path.

**Package plan reuse:** Package Commission Preview uses `PackageIncomePolicy` / existing authorized package definitions (pool + days). No duplicate package plan admin. No hard-coded GOLD/PREMIUM/SILVER/VIP financial amounts in the new settings.

---

## 4. Admin UI (Panel V2)

Route: `admin.finance.commission-settings` (tabs)

1. **Level Unlock Rules** — edit draft bands, live vs draft ladder  
2. **Commission Percentages** — 20 levels, 4 dp, live total  
3. **Package Commission Preview** — read-only impact + package pool distribution  
4. **Configuration History** — versions, publish, authorize/revoke, audits, Associate dry-run structure  

Also: `admin.finance.level-unlock-rules` read-only summary + proposed impact.

POST routes (draft/publish/authorize/revoke/clone) are additive; GET commission-settings remains the primary page.

---

## 5. Member UI

- Eligibility: activation status, qualifying directs, unlocked/locked (of 20), rate chart, next unlock, daily/total credited Level Income (`PackageLevelAccrual`)
- Level Commission Report: **20-level commission unlock** (not 32-rank). Rank ladder labeled separately.
- Dashboard: removed misleading static “poster schedule” copy; rank levels labeled as rank system

---

## 6. Applicability & historical protection

- Publish does **not** authorize posting.
- Authorize requires: applicability ≠ `unspecified`, effective date set, explicit confirmation.
- Applicability options: `future_purchases_only` | `existing_prospective` | `unspecified`
- Impact preview is read-only; **no** historical recalculation/backfill.
- Existing approved purchases keep their package income term snapshots.

---

## 7. Test results

```
Tests:    654 passed (6638 assertions)
Duration: ~210s
Vite production build: SUCCESS (panel-v2 + existing entries)
```

Prior baseline was 648 tests; this feature adds `LevelIncomeRuleAdministrationTest` (6 cases) and keeps live financial behavior unchanged until authorize.

Key coverage: inactive/active unlock scenarios, invalid bands/totals, publish without authorize, authorize/revoke, concurrent-safe version status, historical protection messaging, admin tabs render.

---

## 8. Financial impact preview (proposed draft vs legacy)

| Scenario | Legacy unlocked | Proposed (activated) |
| --- | --- | --- |
| Inactive, 0 directs | 0 | 0 |
| Active, 0 directs | 0 | **4** |
| Active, 1 direct | 4 | 8 |
| Active, 2 directs | 8 | 12 |
| Active, 3 directs | 12 | 16 |
| Active, 4+ directs | 16 / 20 | 20 |

Rates: proposed draft prepopulated with existing 100.0000% chart (unchanged vs baseline).

---

## 9. Rollback plan

1. **Immediate financial rollback:** Revoke `posting_authorized` (Admin → Commission Settings → History) → resolver falls back to hard-coded `CommissionPlan::RATE_BY_LEVEL` + legacy `levelsForCount`.
2. **Config rollback:** Keep prior published version; do not authorize new drafts.
3. **Schema rollback (local only):** `php artisan migrate:rollback` for `2026_11_24_130000_create_level_income_rule_tables` — drops the four tables. Do not run on production without a change window.
4. **Code rollback:** Revert this feature commit/tag; engine constants remain the source of truth when tables are absent (`Schema::hasTable` guard).

---

## 10. Explicit non-actions (per deployment restrictions)

- Did **not** authorize proposed unlock for live posting  
- Did **not** enable Associate Commission posting  
- Did **not** deploy or run production migrations  
- Did **not** recalculate/backfill historical income  
- Did **not** silently overwrite the applicable commission chart for posting  

**Next step for business:** Review impact preview → choose applicability + effective date → publish → separate written approval → authorize posting.
