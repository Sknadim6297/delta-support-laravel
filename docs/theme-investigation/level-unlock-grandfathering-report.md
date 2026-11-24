# Existing Member Level Protection (Grandfathering) — Implementation Report

**Project:** Delta Support  
**Date:** 2026-11-24  
**Scope:** Configurable, versioned unlock grandfathering. **Local only — posting remains unauthorized.**

---

## Authorization readiness

| Item | Status |
|------|--------|
| Implement grandfathering (code + migration) | **GO** to deploy code |
| Capture baseline on production | Ready after deploy (read/write config only; no income posting) |
| Authorize new Level Unlock rules | **NO-GO** until BD approval + capture + publish checklist complete |
| Historical backfill / wallet mutation | **Not performed** |

**Tests:** 711 passed · **Vite:** OK

---

## 1. What was implemented

### Formula

```
protected = max(proposed_unlock, min(snapshot_legacy_unlock, current_legacy_unlock))
```

| Behavior | Result |
|----------|--------|
| Migration would reduce unlock (e.g. ST100026: proposed 0, legacy 4) | Floor keeps **4** while legacy eligibility remains |
| New rules grant more (e.g. ST100027: proposed 8) | Member **gains** to 8 |
| Member loses income-eligible directs after transition | Floor shrinks with `current_legacy` — normal eligibility preserved |
| Member not in snapshot (joined after capture) | Proposed unlock only |

### Versioned / auditable controls

On `level_income_rule_versions`:

- `grandfather_existing_unlock` (bool)
- `grandfather_transition_date`
- `grandfather_captured_at` / `grandfather_captured_by`

Table `level_income_unlock_grandfather_snapshots` stores per-member legacy unlock at capture.

Audit action: `grandfather_baseline_captured`.

Proposed draft `proposed_activation_unlock_v1` **recommends** grandfathering (`true`) but stays **`posting_authorized = false`**. Authorize is blocked until a baseline is captured when grandfathering is enabled.

### Safety boundaries (unchanged)

- Does **not** bypass individual payment controls, emergency stop, package expiry, or ROI terms  
- Does **not** make Tour Only generate ROI/Level Income  
- Tour Only **BV** fix preserved  
- `future_purchases_only` / `existing_prospective` unchanged (no historical backfill)  
- Capture / authorize are separate explicit Admin steps  

### Admin UI

Commission Settings → Level Unlock:

- Toggle: Grandfather existing legacy unlock  
- Capture baseline form (transition date + confirm)  
- Impact sample: **Current / Proposed / Protected**  

Member eligibility shows protected preview when available.

---

## 2. ST100026 / ST100027 verification (tests)

| Member class | Live legacy | Proposed raw | Protected | Outcome |
|--------------|-------------|--------------|-----------|---------|
| **ST100026** (direct qualifies; no personal package) | 4 | 0 | **4** | No silent loss |
| **ST100027** (activated + 1 approved direct) | 4 | 8 | **8** | Gains under new rules |
| After losing qualifying direct | 0 | 0 | **0** | Protection does not freeze forever |

---

## 3. Production readiness checklist

### A. Deploy code (no authorize)

```bash
cd /home/wwwzeont/deltasupport.online
php artisan down
# deploy release
composer install --no-dev --optimize-autoloader
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Verify migration `2026_11_24_180000_add_level_income_unlock_grandfathering` applied.

### B. Business approval gates (still required)

| ID | Decision |
|----|----------|
| BD-Q-01 | Qualification basis |
| BD-Q-02 | Tour Only unlock flag |
| BD-Q-03 | Unlock bands |
| BD-Q-04 | Applicability (`future_purchases_only` recommended for existing 43) |
| BD-Q-05 | `effective_from` |
| BD-Q-07 | Confirm grandfathering + transition date |
| BD-Q-06 | `posting_authorized` only after A–E below |

### C. Pre-authorize sequence (Admin)

1. Confirm draft settings (basis, Tour unlock, bands, grandfather **on**).  
2. Publish with applicability + effective date (**does not** authorize).  
3. **Capture grandfather baseline** at approved transition date.  
4. Review Commission Settings impact: Current / Proposed / Protected.  
5. Run diagnostic:

```bash
php artisan deltasupport:diagnose-level-unlock-business --all --json | tee /tmp/gf-impact.json
php artisan deltasupport:diagnose-level-unlock-business --member=ST100026 --json
php artisan deltasupport:diagnose-level-unlock-business --member=ST100027 --json
```

6. Confirm ST100026 `protected_unlocked_levels >= live`, ST100027 can gain, `posting_authorized=false`.  
7. Only then: explicit **Authorize posting** (separate confirm; `existing_prospective` needs second confirm).  

### D. Post-authorize monitoring (when BD allows)

- Payment controls + emergency stop remain authoritative  
- No historical Level Income recalculation expected  
- Revoke posting authorization returns live accrual to hard-coded legacy  

### E. Do not

- Auto-authorize from capture  
- Backfill past accruals  
- Enable Associate Commission posting  
- Modify production wallets / purchase snapshots from this feature  

---

## 4. Files

| Area | Path |
|------|------|
| Migration | `database/migrations/2026_11_24_180000_add_level_income_unlock_grandfathering.php` |
| Models | `LevelIncomeUnlockGrandfatherSnapshot`, `LevelIncomeRuleVersion` fields |
| Service | `app/Services/LevelIncomeUnlockGrandfathering.php` |
| Wiring | `ReferralLevelEligibility`, `LevelIncomeUnlockImpactAnalyzer`, `LevelIncomeRuleAdministration` |
| Admin | Controller capture action, Commission Settings UI, route `grandfather-capture` |
| Tests | `tests/Feature/LevelIncomeUnlockGrandfatheringTest.php` (+ related updates) |

---

## 5. Bottom line

Existing-member unlock protection is implemented as **versioned, capturable, auditable grandfathering**. It prevents migration-only level reductions, allows gains under new rules, and shrinks with normal eligibility loss. **Posting stays unauthorized** until business completes the checklist above.
