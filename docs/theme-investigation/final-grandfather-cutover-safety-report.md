# Final Grandfather Cutover Safety Report

**Project:** Delta Support — Level Income Option B  
**Date:** 2026-11-27  
**Scope:** Grandfathering preview/runtime consistency, transition/effective alignment, capture freshness, authorization guards, anti-retroactive safety  
**Constraint:** Local only. No production posting authorization. No Associate Commission enablement. No historical backfill.

---

## 1. Executive summary

Production already has a published Option B-derived version (`draft_from_proposed_activation_unlock_v1_054322`) with `existing_prospective`, effective **2026-11-28**, grandfathering enabled, **82** snapshots captured on **2026-11-27**, and **posting authorization OFF**.

This change set hardens cutover safety so that:

1. Preview and runtime agree after capture for members without snapshots.
2. `existing_prospective` authorization requires grandfather transition date = effective date.
3. Capture no longer claims an exact future-date snapshot; future dates are labeled `pre_cutover_current`.
4. Past/current transition dates reconstruct eligibility **as-of** that date.
5. Stale baselines block authorization until recapture (or explicit pre-cutover acknowledgment when appropriate).
6. Snapshots cannot be overwritten after authorization.
7. Effective dates on/before the last succeeded financial business date are rejected (no accidental retroactive authorize).

| Gate | Verdict |
|------|---------|
| **Code deployment** | **GO** (after backup + migrate) |
| **Financial posting authorization** | **NO-GO** without explicit business approval |

---

## 2. Root causes

| ID | Root cause | Impact |
|----|------------|--------|
| RC1 | `previewMember()` applied provisional live-legacy protection even **after** capture when a member had no snapshot row | Preview could show protected floors while `apply()` correctly used proposed-only → admin/member inconsistency |
| RC2 | Authorization did not require `grandfather_transition_date === effective_from` for `existing_prospective` | Production could authorize with mismatched cutover dates |
| RC3 | `captureBaseline()` always read **current** eligibility while storing a (possibly future) transition date | Ops could treat a pre-cutover freeze as an exact future-date snapshot |
| RC4 | No freshness / new-member drift checks between capture and authorize | Silent cutover with incomplete baseline |
| RC5 | No guard against authorizing an effective date already covered by succeeded `package_income_runs` | Risk of appearing to “re-open” processed business dates (system does not backfill, but authorization would be unsafe) |

---

## 3. Confirmed fixes

### 3.1 Preview/runtime consistency

- Before capture: provisional protection remains **preview-only** (`using_provisional_snapshot=true`).
- After capture: missing snapshot → **proposed only** in both `previewMember()` and `apply()`.

### 3.2 Transition vs effective date

- `assertReadyForAuthorization()` for `existing_prospective` requires:
  - `grandfather_transition_date == effective_from`

### 3.3 Capture modes

| Mode | When | Behavior |
|------|------|----------|
| `as_of_transition_date` | transition ≤ today | Reconstruct legacy unlock from members/purchases on/before transition end-of-day |
| `pre_cutover_current` | transition > today | Freeze **current** eligibility for a future cutover; explicitly not an exact future snapshot |

Stored on version: `grandfather_capture_mode`, `grandfather_capture_note` (audited).

### 3.4 Freshness / cutover workflow

`assessBaselineFreshness()` detects:

- New members after capture
- Snapshot vs recomputed legacy drift
- Pre-cutover baselines that must be recaptured once the transition date arrives

Authorization rules:

- `requires_recapture` → hard block
- `requires_pre_cutover_ack` → require `confirm_pre_cutover_baseline`
- After authorization → recapture blocked (unchanged)

**Between authorize and effective cutover:**

- Business dates before `effective_from` stay legacy (applicability).
- New members without snapshots use proposed Option B only.
- Protection formula remains `max(proposed, min(snapshot_legacy, current_legacy))`.
- No snapshot overwrite after authorize.
- No historical backfill.

### 3.5 Anti-retroactive authorization

- If last succeeded `package_income_runs.business_date` exists and `effective_from <= that date` → reject.

### 3.6 Option B formula preserved

- Join unit + personal approved purchase unit; repeats count; sum; ×4 levels; max 20; personal activation required.
- Tour Only may unlock when configured; does not generate unauthorized ROI/commission.
- Grandfather formula unchanged.

---

## 4. Changed files

| File | Change |
|------|--------|
| `app/Services/LevelIncomeUnlockGrandfathering.php` | Preview fix; capture modes; freshness; auth asserts; anti-retroactive |
| `app/Services/ReferralLevelEligibility.php` | As-of direct/purchase helpers |
| `app/Services/LevelIncomeRuleAdministration.php` | `confirmPreCutoverBaseline`; snapshot/summary fields |
| `app/Http/Controllers/Admin/LevelIncomeRuleController.php` | Capture status messaging; pre-cutover confirm |
| `app/Models/LevelIncomeRuleVersion.php` | Capture mode/note fillable |
| `resources/views/admin/finance/commission-settings.blade.php` | Capture honesty, freshness UI, authorize ack |
| `database/migrations/2026_11_27_140000_add_grandfather_capture_mode.php` | New columns |
| `tests/Feature/LevelIncomeUnlockGrandfatheringTest.php` | Cutover safety regressions |

---

## 5. Migration requirements

```bash
php artisan migrate
```

Adds nullable:

- `level_income_rule_versions.grandfather_capture_mode`
- `level_income_rule_versions.grandfather_capture_note`

No financial table changes. No wallet/ledger/accrual mutations.

**Production note:** Existing 82 snapshots remain valid data, but the capture performed on 2026-11-27 for transition 2026-11-28 is a **pre-cutover current freeze**. On/after 2026-11-28, this code will **require recapture as-of the transition date** before authorize.

---

## 6. Test results

| Suite | Result |
|-------|--------|
| `LevelIncomeUnlockGrandfathering` | **17 passed** |
| Related Level Income / Unlock filters | **80 passed** |
| Full automated suite | **754 passed (7244 assertions)** (see `storage/logs/full-test-run-grandfather-safety.txt`) |
| Vite production build | **Successful** |

Covered regressions:

- Future-dated capture labeling
- Mismatched transition/effective dates
- New members after capture
- Eligibility/as-of reconstruction
- Stale pre-cutover on transition day
- Preview/runtime consistency without snapshots
- Authorization guards / no post-auth overwrite
- Already processed business dates
- Existing grandfather entitlement preservation (ST100026-style)

---

## 7. Production deployment steps (code only)

1. Verify deploy target is production app pointing at production DB (ops check).
2. Take a verified DB backup.
3. Deploy this release.
4. Run `php artisan migrate --force`.
5. `php artisan optimize:clear` (then cache config/routes/views as per ops standard).
6. **Do not** authorize posting yet.
7. In Admin → Level Income rules for `draft_from_proposed_activation_unlock_v1_054322` (or current published clone):
   - Confirm applicability `existing_prospective`
   - Confirm `effective_from = 2026-11-28` (or BD-approved date)
   - Confirm grandfathering enabled
8. Run read-only previews:
   - Level unlock diagnostic
   - `php artisan deltasupport:preview-level-income-liability --date=YYYY-MM-DD --applicability=existing_prospective --effective-from=YYYY-MM-DD --json`

---

## 8. Exact safe cutover procedure

**Goal:** Authorize unlock/rate version for prospective use without historical backfill and without silent baseline drift.

### Preferred path (clean cutover)

1. Keep financial calc/credit **OFF** until separate finance GO.
2. On the morning of the effective date (or after that calendar date begins), **before** any armed financial cycle for that date:
   - Set transition date = effective date (`2026-11-28` or approved).
   - **Recapture** grandfather baseline (mode becomes `as_of_transition_date`).
   - Review freshness panel (must be fresh).
3. Confirm:
   - transition date == effective_from
   - snapshot count expected (~all registered members as of that date)
   - members with legacy unlock count reviewed
4. Authorize posting for the Level Income **rule version** only after BD confirmation.
5. Keep Associate Commission posting disabled.
6. Keep wallet crediting off until finance explicitly approves.
7. Spot-check focus members + a no-snapshot new member (proposed-only).
8. First financial run (when later approved) must use a business date **≥ effective_from** and not already succeeded.

### If authorizing before the transition date (not preferred)

1. Capture is `pre_cutover_current` — acknowledge it is **not** an exact future snapshot.
2. New members after capture require `confirm_pre_cutover_baseline` or recapture.
3. Once transition date arrives, **recapture is mandatory** before authorize under the new guards.
4. Do not overwrite snapshots after authorization.

### Post-authorize / pre-effective policy

| Event | Handling |
|-------|----------|
| New member registers | Proposed Option B only (no GF floor) |
| Legacy eligibility lost | Floor shrinks via `min(snapshot, current_legacy)` |
| Legacy eligibility gained | Snapshot floor does not increase |
| Attempt to recapture | Blocked while authorized |
| Business date &lt; effective | Legacy accrual path |
| Business date ≥ effective | Authorized path + grandfather |

---

## 9. Rollback considerations

1. **Revoke posting authorization** in Admin (returns live accrual selection toward legacy hard-coded path per resolver rules).
2. Do **not** delete grandfather snapshots casually; keep audit history.
3. Code rollback to prior release if needed; migration columns are nullable and safe to leave.
4. Never reverse/rewrite wallet ledger or accruals as part of unlock rollback.
5. Do not run financial recovery / backfill to “undo” unlock display.

---

## 10. Outstanding business approvals

| Item | Status |
|------|--------|
| Authorize production Level Income rule posting | Pending BD |
| Enable financial calculation / wallet crediting | Pending finance |
| Associate Commission posting | Must remain disabled |
| Premium / Delta Share package term changes | Out of scope; still pending prior BD items |

---

## 11. GO / NO-GO

### Code deployment — **GO**

Ship the grandfather cutover safety fixes and migration. Safe with posting still unauthorized.

### Financial authorization — **NO-GO**

Do not authorize production posting, arm daily financial automation, enable Associate Commission, or credit wallets without explicit business/finance approval after a fresh as-of baseline on the effective date.

---

**End of report.**
