# Final Cutover — NULL Capture Mode & Diagnostic Fix Report

**Project:** Delta Support — Level Income Option B cutover safety  
**Date:** 2026-11-27  
**Baseline production commit under review:** `908d7db3`  
**Scope:** Fail-closed grandfather capture-mode freshness, Asia/Kolkata cutover boundary, diagnostic published-candidate selection  
**Constraint:** Local only. No deploy, push, financial authorize, Associate Commission enablement, recovery, or production data changes.

---

## 1. Executive summary

Production version `draft_from_proposed_activation_unlock_v1_054322` (ID 3) has **82 snapshots** with `grandfather_capture_mode = NULL`. Freshness previously treated unknown mode as an empty string and skipped mode-specific stale guards, so Admin could show **Baseline Fresh: Yes** incorrectly.

This patch:

1. **Fail-closes** NULL / empty / unrecognized capture modes (`requires_recapture=true`; acknowledgment cannot bypass).
2. Uses **Asia/Kolkata** consistently for grandfather business-date / schedule cutover checks.
3. Blocks authorize when effective date is “today” **after** `00:10` Asia/Kolkata while that date has not succeeded (scheduler concurrency guard).
4. Fixes `deltasupport:diagnose-level-unlock-business` to default to the **latest published** `future_purchases_only` / `existing_prospective` candidate, with explicit `--rule-version`.

| Gate | Verdict |
|------|---------|
| **Code deployment** | **GO** (after backup + migrate if columns missing on target) |
| **Financial posting authorization** | **NO-GO** without BD approval + mandatory recapture in the safe window |

---

## 2. Root causes

| ID | Root cause | Production symptom |
|----|------------|--------------------|
| RC1 | `assessBaselineFreshness()` cast NULL mode to `''` and only special-cased known modes | NULL baselines looked fresh; authorize path could proceed if other checks passed |
| RC2 | Capture/freshness “today” used app/`Carbon::now()` without enforcing package-income timezone | Boundary drift vs financial scheduler (`Asia/Kolkata`, `00:10`) |
| RC3 | As-of reconstruction used UTC `endOfDay()` | As-of window not aligned to Kolkata business day |
| RC4 | Diagnostic hard-coded `proposed_activation_unlock_v1` | Superseded draft selected instead of published `draft_from_proposed_activation_unlock_v1_054322` |

---

## 3. Fixes implemented

### 3.1 NULL / unknown capture mode (fail closed)

- Known modes only: `as_of_transition_date`, `pre_cutover_current`.
- NULL, empty, or any other value ⇒ `is_fresh=false`, `requires_recapture=true`, clear Admin reason.
- `confirm_pre_cutover_baseline` **cannot** bypass.
- Snapshots are **not** silently rewritten or deleted by freshness assessment.
- Unauthorized recapture remains the only way to replace baselines (still blocked after authorize).

### 3.2 Asia/Kolkata cutover boundary (deterministic)

| Rule | Definition |
|------|------------|
| Business timezone | `config('deltasupport.package_income.timezone')` → **Asia/Kolkata** |
| Schedule time | `config('deltasupport.package_income.schedule_time')` → **00:10** |
| Business date B | Calendar date in Asia/Kolkata |
| financial:daily | Processes business date B at 00:10 Asia/Kolkata |
| Safe authorize window for effective `E` | After last succeeded business date `< E`, and **before** 00:10 Asia/Kolkata on `E`. Do **not** authorize after `E` has started or succeeded. |
| Pre-cutover capture | Freezes **current** eligibility for a future transition — **not** a future end-of-day reconstruction |
| Open-day as-of on `E` | Freeze as of capture instant — **not** complete Kolkata end-of-day |

Scheduler is **not** stopped or disabled by this patch.

### 3.3 Diagnostic version selection

Default order:

1. `--rule-version=` (explicit)
2. Latest **published** with applicability `future_purchases_only` or `existing_prospective`
3. Seeded `proposed_activation_unlock_v1` draft fallback

Report now distinguishes:

- published candidate
- authorized runtime version
- hard-coded legacy source
- candidate effective date, applicability, authorization, grandfather freshness

Command remains read-only.

### 3.4 Business rules preserved

Option B units/bands/activation, Tour Only unlock configurability, grandfather formula `max(proposed, min(snapshot, current_legacy))`, package terms, rates, ROI, wallets, ledgers, Associate Commission authorization — **unchanged**.

---

## 4. Changed files

| File | Change |
|------|--------|
| `app/Services/LevelIncomeUnlockGrandfathering.php` | Fail-closed modes; Kolkata helpers; schedule cutover assert; capture notes |
| `app/Services/ReferralLevelEligibility.php` | As-of end-of-day in Asia/Kolkata |
| `app/Services/LevelIncomeUnlockImpactAnalyzer.php` | `resolveCandidateVersion()`, `versionSources()` |
| `app/Console/Commands/DiagnoseLevelUnlockAndBusinessVolume.php` | `--rule-version`; published candidate default; source labels |
| `resources/views/admin/finance/commission-settings.blade.php` | Baseline fresh wording for NULL/recapture |
| `tests/Feature/LevelIncomeUnlockGrandfatheringTest.php` | NULL/unknown mode, schedule window regressions |
| `tests/Feature/LevelUnlockDiagnosticDiscrepancyTest.php` | Published/explicit diagnostic selection |

Migration already present from prior patch (if not on production yet):

- `database/migrations/2026_11_27_140000_add_grandfather_capture_mode.php`

---

## 5. Migration requirements

If production does not yet have the columns:

```bash
php artisan migrate --force
```

Adds nullable `grandfather_capture_mode` / `grandfather_capture_note`.  
Does **not** mutate snapshots, wallets, or accruals. Existing NULL modes remain NULL until ops **recaptures**.

---

## 6. Test results

| Suite | Result |
|-------|--------|
| Grandfather + diagnostic focused | **29+ passed** (plus CombinedLevel cutover-window fix) |
| Full automated suite | **758 passed (7276 assertions)** |
| Vite production build | **Successful** |

Covered:

- NULL / empty / unknown capture modes
- Authorize rejected despite acknowledgment
- Valid recapture restores freshness
- Asia/Kolkata schedule cutover / concurrency
- No retroactive effective date
- Diagnostic published default + explicit `--rule-version`
- Existing grandfather protection behavior

---

## 7. Exact safe production cutover procedure

**Current production facts:** effective/transition `2026-11-28`; snapshots 82; capture mode NULL; last succeeded `2026-11-27`; posting OFF; scheduler 00:10 Asia/Kolkata.

### Mandatory before any authorize

1. Deploy this code patch (posting still OFF).
2. Confirm Admin Baseline Fresh shows **No — recapture required** for NULL mode.
3. Confirm diagnose defaults to published candidate:

```bash
php artisan deltasupport:diagnose-level-unlock-business --json
php artisan deltasupport:diagnose-level-unlock-business --rule-version=draft_from_proposed_activation_unlock_v1_054322 --json
```

4. **Recapture** grandfather baseline with transition date = `2026-11-28` (= effective_from) in the safe window:
   - After `2026-11-27` financial success (already true)
   - **Before** `2026-11-28 00:10` Asia/Kolkata
5. Confirm capture mode is `as_of_transition_date` (or documented open-day freeze note if captured on 2026-11-28 before 00:10).
6. Confirm freshness `is_fresh=true`.
7. Only then consider BD authorize of the **rule version** (still separate from wallet-credit arming).

### Blocker if safe window is missed

If it is already after `00:10` Asia/Kolkata on `2026-11-28` and that business date has **not** succeeded under the intended rules, **do not authorize**. Wait for cycle completion / ops guidance rather than bypassing guards. Do not stop the scheduler from application code.

---

## 8. Remaining blockers / outstanding approvals

| Item | Status |
|------|--------|
| Recapture NULL-mode baseline on production | Required before authorize |
| Safe window before 00:10 on effective date | Ops timing dependency |
| BD authorize Level Income rule posting | Pending |
| Finance enable calc/credit / arm | Pending / NO-GO here |
| Associate Commission posting | Must remain disabled |
| Premium / Delta Share term changes | Out of scope |

---

## 9. GO / NO-GO

### Code deployment — **GO**

Deploy the fail-closed capture-mode + diagnostic + Kolkata cutover guards. Safe with posting unauthorized; surfaces the production NULL-mode defect correctly.

### Financial authorization — **NO-GO**

Do not authorize production posting until:

1. Recapture completes with a known capture mode  
2. Freshness is green  
3. Authorize occurs inside the documented Asia/Kolkata safe window  
4. Explicit business/finance approval is recorded  

---

**End of report.**
