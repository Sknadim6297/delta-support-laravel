# Level Unlock Production Diagnostic Discrepancy — Root Cause & Fix Report

**Project:** Delta Support  
**Date:** 2026-11-24  
**Scope:** Read-only investigation + local diagnostic/consistency fixes. **No authorize, no accrual, no production financial mutation.**

---

## Authorization readiness

| Gate | Status |
|------|--------|
| Deploy diagnostic consistency fix | **GO** (non-financial) |
| Authorize `proposed_activation_unlock_v1` | **NO-GO** |
| Reason | Sample showed entitlement **losses** (live > proposed). Do not authorize while losses exist unless BD explicitly accepts them. |

**Tests:** 702 passed · **Vite:** OK

---

## 1. Root causes

### 1.1 ST100026 — live 4 / proposed 0

**Cause (configuration + ladder semantics, not a wiped entitlement):**

| Context | Behavior |
|---------|----------|
| **Live legacy** | Unlock = `legacyLevelsForCount(income-eligible unique directs)`. **Personal activation is ignored.** 1 income-eligible direct → **4 levels**. |
| **Proposed bands** | Every band has `requires_personal_activation = true`. Without a personal package that passes the proposed filter → **0 levels**. |

Typical ST100026 pattern:

1. Sponsor has ≥1 income-eligible qualifying direct → live **4**.
2. Sponsor has **no** personal approved package matching proposed activation (`approved_package_directs` + Tour unlock flag) → proposed **0**.
3. Live posting was **never** changed; preview only.

**Entitlement rule:** Live 4 must remain until BD accepts a policy that would reduce unlock after authorize. Diagnostic now flags this as **ENTITLEMENT REDUCTION RISK**.

### 1.2 ST100027 — “4 in first table / 0 in second”

**Cause: misleading diagnostic presentation (not two different live unlock values).**

Previous diagnostic built two tables from related but poorly labeled fields:

- Table 1 “Current” = summaries unlock  
- Table 1 “Reason” = preview `locked_reason` (live narrative, **not** why proposed differs)  
- Table 2 “Unlocked” = same summaries unlock when `activated && unlock==0`

A member with live **4** cannot honestly appear as Unlocked **0** in the same run. The apparent contradiction came from:

1. Reading **Proposed = 0** in table 1 as if it were a second “current” reading, and/or  
2. Comparing Current (4) to Proposed (0) across columns without an entitlement-delta explanation.

**Fix:** One analyzer, one field `live_unlocked_levels` for every table; explicit `delta_reason`; consistency check in JSON.

### 1.3 Context inconsistency (item 3)

| Surface | Before | After |
|---------|--------|-------|
| Diagnostic | Mixed summaries + preview | `LevelIncomeUnlockImpactAnalyzer` only |
| Member UI | Rule mode only | + `eligibility_context.kind` |
| Accrual | Purchase-scoped (already) | Same + exposed `posting_context` via analyzer |
| Preview “current” | Could follow `unlockedLevels()` (flips if authorized) | Always **legacy entitlement** for `currently_unlocked_levels` |

Contexts now labeled:

- `live_legacy`
- `proposed_simulation`
- `authorized_display_without_purchase`
- `purchase_accrual` (source purchase id + business date)

### 1.4 Tour Only BV (4 × → 200 USDT)

`PackageBusinessVolumeEligibility` + `TeamBusinessCalculationService` already count each approved Tour Only USDT snapshot **once**. Diagnostic now emits a purchase-level audit (`tour_only_bv_audit`) with ids, members, amounts, and `counted_once`.

### 1.5 Applicability vs 43 approved-package members

| Policy | Effect on existing approved members/purchases |
|--------|-----------------------------------------------|
| **`future_purchases_only`** (recommended) | All **existing** approved purchases keep **legacy** unlock/rates. Only purchases approved on/after `effective_from` use authorized rules. |
| **`existing_prospective`** | From `effective_from` **business date** forward, authorized rules apply to commissions from existing + new purchases. Prior business dates stay legacy. **No historical backfill.** |

Projection is included in the diagnostic (`applicability_projection`). Does not authorize.

---

## 2. Client qualification policy comparison

| Basis | Unique members? | Live default | Proposed draft | Client fit |
|-------|-----------------|--------------|----------------|------------|
| Registered directs | Yes | No | No | Expands unlock without purchase — high risk |
| Approved package directs | Yes | No | **Yes** | Matches “approved packages” + Tour unlock flag |
| Income-eligible directs | Yes | **Yes** | No | Current commission semantics |
| Approved purchase **count** | **No** | No | No | Can inflate unlock vs unique-direct bands |

Unique-member counting and purchase-count remain explicitly separate.

---

## 3. Fixes shipped (local)

| Area | Change |
|------|--------|
| `LevelIncomeUnlockImpactAnalyzer` | Unified live/proposed/display/accrual contexts; gain/loss/unchanged; Tour BV audit; applicability projection; policy comparison |
| Diagnostic command | Rewritten: losses/gains tables, BV purchase list, `--all`, consistency guard, authorize NO-GO when losses exist |
| `LevelIncomeDirectQualification::memberPreview` | Canonical legacy current; `proposed_delta_reason`; entitlement_reduction flag |
| `ReferralLevelEligibility::forMember` | Explicit `eligibility_context` (+ posting context when purchase-scoped) |
| Member eligibility UI | Shows eligibility context + proposed delta reason |
| Tests | `LevelUnlockDiagnosticDiscrepancyTest` (8 cases) covering ST100026/027 classes, BV 200, contexts, impact, applicability |

**Not changed:** wallets, ROI terms, commission rows, purchase snapshots, payment controls, `posting_authorized`.

---

## 4. Member-level impact (how to produce on production)

```bash
cd /home/wwwzeont/deltasupport.online

# Full universe impact (read-only)
php artisan deltasupport:diagnose-level-unlock-business --all --json > /tmp/level-unlock-impact.json

# Focus discrepancies
php artisan deltasupport:diagnose-level-unlock-business --member=ST100026 --json
php artisan deltasupport:diagnose-level-unlock-business --member=ST100027 --json
```

JSON sections:

- `members_gaining_levels`
- `members_losing_levels`
- `members_unchanged_count`
- `tour_only_bv_audit.purchases` (expect 4 rows totaling `200.0000`)
- `entitlement_warnings.authorize_blocked_while_losses_exist`
- `applicability_projection`

---

## 5. Production verification commands (read-only)

```bash
cd /home/wwwzeont/deltasupport.online

# After deploying code (migrate only if 170000 not yet applied)
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan deltasupport:diagnose-level-unlock-business --all --json | tee /tmp/level-unlock-impact.json
php artisan deltasupport:diagnose-level-unlock-business --member=ST100026 --json
php artisan deltasupport:diagnose-level-unlock-business --member=ST100027 --json

# Confirm
# - consistency.ok === true
# - tour_only_bv_audit.purchase_count === 4
# - tour_only_bv_audit.total_usdt === "200.0000"
# - tour_only_bv_audit.counted_once === true
# - ST100026: live_unlocked=4, proposed=0, entitlement_reduction=true, delta_reason mentions personal activation
# - ST100027: live_unlocked identical in focus.consistency_check; not in activated_with_zero_live_unlock with a different unlock
# - proposed_version.posting_authorized === false
```

**Do not run:** `financial:daily` posting, authorize posting, backfill, Associate Commission enablement.

---

## 6. Settings still requiring business approval

| ID | Topic | Readiness |
|----|-------|-----------|
| BD-Q-01 | Qualification basis | NO-GO until signed |
| BD-Q-02 | Tour Only unlock flag | NO-GO until signed |
| BD-Q-03 | Proposed bands (0 directs → 1–4 …) | NO-GO until signed |
| BD-Q-04 | Applicability | Prefer `future_purchases_only` for the 43 |
| BD-Q-05 | `effective_from` | Required before authorize |
| BD-Q-06 | `posting_authorized` | **Blocked** while `members_losing_levels` non-empty unless BD accepts reductions |
| BD-Q-07 | **Entitlement grandfathering** | Required if ST100026-class members must keep live unlock under new bands |

---

## 7. Bottom line

- **ST100026:** Proposed preview correctly shows 0 when personal activation fails under proposed bands; live legacy still grants 4 because activation is ignored. Not a silent live removal.  
- **ST100027:** Table inconsistency was diagnostic labeling/column confusion; fixed with a single `live_unlocked_levels` source.  
- **Tour Only 200 USDT:** Verified countable once via BV audit.  
- **Authorize:** **NO-GO** until BD addresses entitlement reductions (BD-Q-06/07) and remaining BD-Q items.
