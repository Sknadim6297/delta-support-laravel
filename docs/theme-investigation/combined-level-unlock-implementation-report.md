# Combined Level Unlock Qualification — Implementation Report

**Project:** Delta Support (`C:\xampp\htdocs\delta_support`)  
**Date:** 2026-11-26  
**Scope:** Configurable `combined_joining_and_purchase` qualification basis (joining + purchase contribute independently).  
**Constraints:** Local only — no deploy, no financial posting authorization, no production data mutation, no historical income recalculation. Grandfathering not re-implemented (already present).

---

## Verdict

| Item | Status |
|------|--------|
| Canonical basis key | **`combined_joining_and_purchase`** (singular) |
| Legacy plural alias | Normalized in code + data migration |
| Configurable joining / purchase contributions | **Implemented** |
| Direct-member vs personal purchase scope | **Implemented** (`personal_approved_purchases` added) |
| Unique vs repeated purchase counting | **Implemented** |
| Tour Only unlock flag | **Implemented** (existing flag reused) |
| Max 20 levels | **Enforced** via resolver / eligibility |
| Admin Commission Settings / impact sample | **Updated** (joining / purchase / combined credits) |
| Impact analyzer + diagnostics | **Extended** (credits + per-level lock reasons) |
| Grandfathering | **Preserved** (`protected = max(proposed, min(snapshot_legacy, current_legacy))`) |
| Live posting | **Unchanged / unauthorized** until BD formula approval + authorize |
| Full suite | **741 passed (7151 assertions)** |
| Vite build | **Successful** |

Production investigation (commit `e3f6e9c0`) correctly found no combined basis in production. Local implementation closes that gap without enabling posting.

---

## 1. What production was missing

Production supported single bases only:

- `registered_directs`
- `approved_package_directs`
- `income_eligible_directs`
- `approved_package_purchase_count`

There was no way for **direct joining and approved package purchase** to contribute independently in one unlock metric.

---

## 2. Formula (configurable, not BD-approved as live)

Canonical basis: **`combined_joining_and_purchase`**.

| Control | Meaning |
|---------|---------|
| `combined_joining_enabled` | Include registered-direct joining contribution |
| `combined_purchase_enabled` | Include purchase contribution |
| `combined_purchase_scope` | `approved_package_directs` \| `income_eligible_directs` \| `approved_package_purchase_count` \| **`personal_approved_purchases`** |
| `combined_repeat_purchase_mode` | `unique_directs` \| `each_approved_purchase` |
| `combined_double_credit_policy` | `prevent_overlap` \| `allow_sum` \| `max_only` |
| `combined_joining_unit_value` | Weight 0–100 |
| `combined_purchase_unit_value` | Weight 0–100 |
| `tour_only_qualifies_for_unlock` | Existing Tour Only unlock inclusion flag |
| Unlock bands | Existing versioned bands; personal activation per band |
| Cap | `min(unlocked, 20)` |

**Policies:**

- **`prevent_overlap`:** for direct-member purchase scopes, joining credits only non-purchasing directs, then sum weighted joining + purchase. Personal purchase scope has no entity overlap with joining, so joining is not reduced.
- **`allow_sum`:** sum weighted joining + purchase (may double-credit the same direct).
- **`max_only`:** larger of the two weighted contributions.

Pending/rejected purchases never count. Personal activation remains a separate band gate (member’s own approved purchase under the active filter).

**Grandfathering (unchanged):**  
`protected = max(proposed, min(snapshot_legacy, current_legacy))`

---

## 3. Compatibility

- Older local drafts storing `combined_joining_and_purchases` (plural) normalize to singular via `normalizeBasis()` and migration `2026_11_26_140000_normalize_combined_joining_and_purchase_basis.php`.
- Deprecated constants `BASIS_COMBINED_JOINING_AND_PURCHASES` / `QUAL_COMBINED_JOINING_AND_PURCHASES` alias the singular value.

---

## 4. Migrations

| Migration | Role |
|-----------|------|
| `2026_11_24_190000_add_combined_level_unlock_qualification.php` | Combined columns on `level_income_rule_versions` (already present) |
| `2026_11_26_140000_normalize_combined_joining_and_purchase_basis.php` | Rename stored plural → singular |
| Grandfathering migrations | **Not modified** |

Run locally:

```bash
php artisan migrate
```

---

## 5. Changed / extended files (this pass)

| File | Role |
|------|------|
| `app/Services/LevelIncomeDirectQualification.php` | Singular basis, plural alias, personal purchase scope, combined preview mode |
| `app/Models/LevelIncomeRuleVersion.php` | Singular constant + deprecated alias |
| `app/Services/LevelIncomeUnlockImpactAnalyzer.php` | Combined credits in analysis, policy entry, per-level lock reasons |
| `app/Services/LevelIncomeRuleAdministration.php` | Basis check + impact sample credits |
| `app/Console/Commands/DiagnoseLevelUnlockAndBusinessVolume.php` | Combined metrics, focus tables, locked-level reasons |
| `resources/views/admin/finance/commission-settings.blade.php` | Impact sample columns for joining/purchase/combined |
| `database/migrations/2026_11_26_140000_normalize_combined_joining_and_purchase_basis.php` | Key normalization |
| `tests/Feature/CombinedLevelUnlockQualificationTest.php` | Expanded scenario coverage (15 tests) |
| `docs/theme-investigation/combined-level-unlock-implementation-report.md` | This report |

Already wired (verified, not rebuilt): resolver, referral eligibility, member dashboard, eligibility page, rule controller validation, grandfathering services.

---

## 6. Admin / member / diagnostics

**Admin:** Commission Settings → Unlock tab — select Combined basis; configure contributions, scope (including personal purchases), repeat mode, double-credit policy, unit values. Impact sample shows joining / purchase / combined credits plus current / proposed / protected.

**Member:** Dashboard + finance eligibility continue to show combined breakdown when authorized version uses combined mode.

**Diagnostic:**

```bash
php artisan deltasupport:diagnose-level-unlock-business --all
php artisan deltasupport:diagnose-level-unlock-business --member=ST100026
```

Shows joining/purchase raw+weighted credits, combined metric, live/proposed/protected, and exact per-level lock reasons (activation / metric shortfall / band coverage).

---

## 7. Tests

### Combined (`CombinedLevelUnlockQualificationTest`) — 15 passed

- Live legacy unchanged while combined staged  
- prevent_overlap / allow_sum / max_only  
- Repeat each purchase  
- Authorized combined + grandfathering  
- Admin save without authorize  
- UI controls (incl. personal purchase label)  
- Singular key + plural alias  
- Joining-only without purchase  
- Personal purchase scope  
- Pending/rejected excluded  
- Tour Only flag on/off  
- existing_prospective + 20-level cap  
- Impact analyzer credits + lock reasons  

### Full suite

```
Tests:    741 passed (7151 assertions)
Duration: ~143s
```


Logs: `storage/logs/combined-unlock-test-run.txt`, `storage/logs/combined-unlock-junit.xml`

### Vite

```
vite build — success (~10.7s)
```

---

## 8. Remaining business decisions (do not authorize until confirmed)

1. Exact live double-credit policy (`prevent_overlap` vs `allow_sum` vs `max_only`).  
2. Joining vs purchase unit weights.  
3. Purchase scope under combined (direct unique / income-eligible / purchase rows / personal).  
4. Repeat mode (unique vs each approved purchase).  
5. Whether both contributions must be enabled for the approved rule.  
6. Band threshold meaning under weighted metrics.  
7. Tour Only unlock under the chosen purchase scope.  
8. Grandfathering capture date / floor on combined go-live.  
9. Applicability: `future_purchases_only` vs `existing_prospective`.  
10. Explicit written BD approval before `posting_authorized = true`.

---

## 9. Explicit non-actions

- No deploy  
- No production authorize / financial job execution  
- No wallet / ledger / ROI term / purchase snapshot mutation  
- No grandfathering redesign  
- No Sub-Admin / financial-control regressions introduced intentionally  

---

## 10. Production deployment instructions (do not run here)

1. Deploy code including both combined migrations (columns + basis key normalize).  
2. `php artisan migrate`  
3. Confirm draft Combined settings remain **unauthorized**.  
4. Smoke: Admin Unlock tab shows Combined + personal scope; diagnostic `--member` shows joining/purchase credits and lock reasons.  
5. Only after written BD formula approval: publish → capture grandfather baseline if enabled → authorize with applicability confirmation.  
6. Do **not** enable financial posting or run financial jobs as part of this deploy.

**Implemented and verified locally only.**
