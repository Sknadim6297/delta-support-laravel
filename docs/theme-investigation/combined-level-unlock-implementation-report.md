# Combined Level Unlock Qualification — Implementation Report

**Project:** Delta Support (`C:\xampp\htdocs\delta_support`)  
**Date:** 2026-11-24  
**Scope:** Direct Joining and Package Purchase contribute independently to Level Unlock (combined qualification).  
**Constraints:** Local only — no deploy, no financial posting authorization, no production data mutation, no historical income recalculation.

---

## Verdict

| Item | Status |
|------|--------|
| Gap vs single-basis-only qualification | **Closed** — new basis `combined_joining_and_purchases` |
| Configurable joining + purchase contributions | **Implemented** (versioned / draft-safe) |
| Purchase scope + repeat-purchase modes | **Implemented** |
| Double-credit policies | **Implemented** (`prevent_overlap` / `allow_sum` / `max_only`) |
| Admin settings UI | **Implemented** |
| Member dashboard + eligibility breakdown | **Implemented** |
| Financial resolver / eligibility integration | **Implemented** (authorized version only) |
| Grandfathering compatibility | **Verified** (combined authorize + capture path in tests) |
| Live / production posting behavior | **Unchanged** — still legacy until BD approves formula + authorize |
| Full test suite | **719 passed** (6992 assertions) |
| Combined qualification tests | **8 passed** |
| Vite build | **Successful** |
| Authorize combined live | **NO-GO** until client approves exact formula |

---

## 1. What was missing (pre-change)

Qualification supported **one metric at a time** via `direct_qualification_basis`:

- `registered_directs`
- `approved_package_directs`
- `income_eligible_directs`
- `approved_package_purchase_count`

There was no way for **joining and purchases to contribute independently** in one unlock metric, nor Admin controls for contribution weights, purchase scope under combined mode, repeat counting, or double-credit policy.

Grandfathering and single-basis versioned qualification were already deployed; this work extends them without re-opening that surface.

---

## 2. Newly implemented features

### 2.1 Combined qualification basis

New basis: `combined_joining_and_purchases`.

When selected on a rule version, unlock metric is computed from independently configurable contributions:

| Control | Options / meaning |
|---------|-------------------|
| `combined_joining_enabled` | Include registered-direct (joining) contribution |
| `combined_purchase_enabled` | Include purchase contribution |
| `combined_purchase_scope` | `approved_package_directs` \| `income_eligible_directs` \| `approved_package_purchase_count` |
| `combined_repeat_purchase_mode` | `unique_directs` \| `each_approved_purchase` |
| `combined_double_credit_policy` | `prevent_overlap` \| `allow_sum` \| `max_only` |
| `combined_joining_unit_value` | Weight multiplier for joining (0–100) |
| `combined_purchase_unit_value` | Weight multiplier for purchases (0–100) |

**Policy behavior (coded, not yet BD-approved as live formula):**

- **`prevent_overlap`:** joining counts only directs who do **not** already contribute via purchase; then sum weighted joining + purchase.
- **`allow_sum`:** joining + purchase may credit the same direct twice.
- **`max_only`:** use the larger of the two weighted contributions.

Tour Only unlock remains gated by the existing `tour_only_qualifies_for_unlock` flag (unlock ≠ ROI/commission generation).

### 2.2 Safety / authorization posture

- Combined columns are staged on draft `proposed_activation_unlock_v1` **without** switching live `direct_qualification_basis` to combined.
- Live accrual continues to use **legacy** income-eligible unique directs until a version with `posting_authorized = true` and basis = combined is explicitly authorized.
- Admin preview surfaces `combined_requires_business_approval = true`.
- Saving combined with both contributions disabled is rejected when basis is combined.
- **No historical ledger / wallet / income recalculation** in this work.

### 2.3 Admin settings

Commission Settings → Level Unlock Rules (draft editable):

- Basis select includes **Combined: direct joining + package purchases**
- Combined contribution checkboxes, scope, repeat mode, double-credit policy, unit values
- Preview stats show staged combined contributions with “Requires basis=combined + BD formula approval”
- Settings clone with drafts; audited via existing version admin path

### 2.4 Member surfaces

- **Dashboard:** when `qualification_breakdown.mode === combined`, shows joining / purchase / combined total + formula note.
- **Finance eligibility:** joining metric + purchase metric stats from breakdown.
- Data sourced from `ReferralLevelEligibility::forMember()` → `LevelIncomeDirectQualification::breakdownForVersion()`.

### 2.5 Resolver / eligibility / grandfathering

- `LevelIncomeDirectQualification` routes combined basis through `combinedBreakdown()`.
- `ReferralLevelEligibility` attaches `qualification_breakdown` for authorized (and preview) paths.
- Accrual remains purchase-scoped via existing eligibility APIs.
- Grandfathering: combined authorize + baseline capture exercised in `test_authorized_combined_unlock_with_grandfathering_compatibility` (uses existing floor / snapshot machinery).

---

## 3. Schema / migration

**Migration:** `database/migrations/2026_11_24_190000_add_combined_level_unlock_qualification.php`  
**Local status:** Ran (batch 47)

| Column | Type | Default (schema) |
|--------|------|------------------|
| `combined_joining_enabled` | boolean | `false` |
| `combined_purchase_enabled` | boolean | `false` |
| `combined_purchase_scope` | string(64) | `approved_package_directs` |
| `combined_repeat_purchase_mode` | string(32) | `unique_directs` |
| `combined_double_credit_policy` | string(32) | `prevent_overlap` |
| `combined_joining_unit_value` | unsignedSmallInteger | `1` |
| `combined_purchase_unit_value` | unsignedSmallInteger | `1` |

Draft seed on `proposed_activation_unlock_v1` (still unauthorized; **does not** set live basis to combined): flags joining+purchase enabled with prevent_overlap / unique_directs / unit 1 — staged tooling defaults only.

---

## 4. Changed / added files

### Added

| File | Role |
|------|------|
| `database/migrations/2026_11_24_190000_add_combined_level_unlock_qualification.php` | Versioned combined columns + draft staging |
| `tests/Feature/CombinedLevelUnlockQualificationTest.php` | 8 feature tests |
| `docs/theme-investigation/combined-level-unlock-implementation-report.md` | This report |

### Modified

| File | Role |
|------|------|
| `app/Models/LevelIncomeRuleVersion.php` | Constants, fillable, casts for combined fields |
| `app/Services/LevelIncomeDirectQualification.php` | Combined basis, breakdown, policies, labels |
| `app/Services/LevelIncomeRuleAdministration.php` | Save/clone/preview combined settings; BD approval flag |
| `app/Services/ReferralLevelEligibility.php` | Expose qualification breakdown |
| `app/Http/Controllers/Admin/LevelIncomeRuleController.php` | Validate + persist combined fields |
| `app/Http/Controllers/MemberController.php` | Pass level unlock (incl. breakdown) to dashboard |
| `app/Http/Controllers/Member/WalletController.php` | Eligibility qualification breakdown |
| `resources/views/admin/finance/commission-settings.blade.php` | Combined Admin controls |
| `resources/views/member/dashboard.blade.php` | Combined metric display |
| `resources/views/member/finance/eligibility.blade.php` | Joining / purchase metric stats |

---

## 5. Test results

### Combined qualification (`CombinedLevelUnlockQualificationTest`)

| Test | Result |
|------|--------|
| Live legacy unchanged while combined settings are staged | PASS |
| Combined prevent_overlap counts join + purchase without double credit | PASS |
| Combined allow_sum can double-credit same direct | PASS |
| Combined max_only uses larger contribution | PASS |
| Repeat each_purchase counts multiple rows | PASS |
| Authorized combined unlock with grandfathering compatibility | PASS |
| Admin can save combined settings without authorizing | PASS |
| Commission settings shows combined controls | PASS |

### Full suite

```
Tests:    719 passed (6992 assertions)
Duration: ~216s
```

(JUnit log: `storage/logs/full-junit.xml`; console: `storage/logs/full-test-run.txt`)

### Vite

```
vite build — success (~8.6s)
```

---

## 6. Remaining business decisions (unauthorized until confirmed)

Do **not** treat staged defaults as the approved live formula. Client must confirm before authorize:

1. **Exact qualification formula** — which double-credit policy is live (`prevent_overlap` vs `allow_sum` vs `max_only`).
2. **Contribution weights** — joining unit value vs purchase unit value (currently staged at 1/1).
3. **Purchase scope under combined** — approved unique / income-eligible unique / purchase-row count.
4. **Repeat purchases** — unique purchasing directs vs each approved purchase row.
5. **Whether both contributions must be on** — joining-only, purchase-only, or both for the approved rule.
6. **Interaction with proposed unlock bands** — whether band thresholds still mean “N directs” under a weighted / double-credit metric, or bands need redefinition.
7. **Tour Only under combined purchase scope** — confirm `tour_only_qualifies_for_unlock` behavior for the chosen purchase scope.
8. **Grandfathering on combined go-live** — confirm capture date, floor policy, and whether existing members keep current unlock when metric formula changes.
9. **Applicability** — `future_purchases_only` vs `existing_prospective` when combined is authorized.
10. **Explicit authorize gate** — no production authorize / posting until written BD approval of the formula above.

---

## 7. Explicit non-actions (this delivery)

- No deploy  
- No `posting_authorized` flip for combined (or any) live rule outside isolated tests  
- No production DB writes / member unlock recalculation jobs  
- No historical income / wallet / ledger rebuild  
- Grandfathering implementation not re-done (compatibility only)

---

## 8. Recommended next step (ops / BD)

1. Client signs off the exact combined formula (policy + scope + repeat + units + bands).  
2. Update draft version to that formula; publish with chosen applicability.  
3. If grandfathering required: capture baseline, then authorize posting.  
4. Deploy migration + code only after that approval — still separate from this local implementation.
