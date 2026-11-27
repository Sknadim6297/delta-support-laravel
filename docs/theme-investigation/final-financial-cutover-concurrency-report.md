# Final Financial Cutover — Concurrency Verification Report

**Project:** Delta Support — Level Income Option B cutover concurrency  
**Date:** 2026-11-27  
**Baseline production commit under review:** `45b586fd`  
**Scope:** Shared mutual exclusion between financial execution and Level Income authorize / revoke / grandfather capture; open-day as-of instant; authorize guidance  
**Constraint:** Local only. No deploy, push, financial authorize, Associate Commission enablement, recovery, scheduler disablement, or production data changes.

---

## 1. Executive summary

Production `45b586fd` used `Cache::lock('package-income-'.$businessDate, 120)` inside financial execution and `lockForUpdate()` on rule rows during authorize/capture, but **did not share a mutex** between those paths. Schedule-time guards (before `00:10` Asia/Kolkata) are **time-based checks only** — they do not stop a concurrent financial process that already holds (or is about to take) work for the same business date.

This patch introduces `FinancialBusinessDateLock` as the shared exclusive lock on key `package-income-{YYYY-MM-DD}`:

| Gate | Verdict |
|------|---------|
| **Code deployment** | **GO** (local verification complete; deploy separately after backup) |
| **Financial posting authorization** | **NO-GO** — concurrency is necessary but not sufficient; BD approval, safe window, fresh baseline, and ops readiness still required |

---

## 2. Finding: production concurrency gap (source-level)

### 2.1 What already existed at `45b586fd`

| Mechanism | What it protects | What it does **not** protect |
|-----------|------------------|------------------------------|
| `Cache::lock('package-income-'.$date, 120)` in `PackageIncomeAccrual` | Duplicate financial runs for the same date | Rule authorize/revoke/capture during plan/credit |
| `lockForUpdate()` on `LevelIncomeRuleVersion` | Concurrent authorize/revoke/capture on the same row | Financial plan reading rules mid-mutation |
| Schedule cutover window (`assertScheduleCutoverWindowSafe`) | Authorize after `00:10` on effective date (when date not yet succeeded) | Concurrent process that started before `00:10` or holds lock past schedule |
| Grandfather DB row lock during capture | Snapshot write races on the version row | Financial execution observing mid-capture rules |

### 2.2 Race that mattered

Financial execution previously planned **outside** a shared lock with authorize (or held only a short TTL that could expire mid-cycle). Authorize could flip `posting_authorized` / clear resolver cache while a run was still planning or crediting → **mixed rule versions** within one business-date run.

**Time-based checks ≠ mutual exclusion.** The `00:10` guard reduces ops risk; it does not serialize processes.

---

## 3. Entry-point trace

### 3.1 Financial execution

| Path | Entry | Lock behavior after patch |
|------|-------|---------------------------|
| Scheduler daily | `financial:daily` → `PackageIncomeAccrual::runControlled` → `executeControlledPlan` | Non-dry-run: acquire `package-income-{date}` **before** `plan()` + credit; release in `finally` |
| Yesterday recover | `financial:daily --recover-yesterday` / `financial:recover` | Same controlled path + shared lock |
| Historical Standard | `runStandardHistorical` → `executeControlledPlan` | Shared lock (non-dry-run) |
| Tour recovery | Tour recovery services → controlled accrual | Shared lock via accrual |
| Legacy accrue | `package-income:accrue` → `accrue()` | Shared lock around plan+credit |
| Local purchase accrue | `accruePurchases()` | Shared lock |
| Dry-run / preview | `runControlled(..., dryRun=true)`, `plan()`, preview APIs | **No** exclusive lock (read-only) |

### 3.2 Rule mutation

| Path | Entry | Lock order |
|------|-------|------------|
| Authorize | `LevelIncomeRuleAdministration::authorizePosting` | Sorted `package-income-{today,effective,transition}` locks → DB txn + `lockForUpdate()` → freshness/effective recheck → authorize → release cache locks in `finally` |
| Revoke | `revokePostingAuthorization` | Same cache-then-DB order |
| Grandfather capture | `LevelIncomeUnlockGrandfathering::captureBaseline` | Locks `{today, transition}` → DB txn + version row lock → snapshot → release in `finally` |
| Publish / draft save | Administration publish/save | **No** financial date lock (does not flip `posting_authorized`; runtime still uses authorized version only) |

### 3.3 Resolver / publication / scheduler

- `LevelIncomeRuleResolver` reads authorized version; cleared on authorize/revoke/capture.
- Publication alone does not change live posting rules.
- Scheduler registration (`DailyFinancialScheduleRegistrar`, `InProcessScheduleTick`) unchanged — **not disabled**.

---

## 4. Shared locking design

### 4.1 `FinancialBusinessDateLock`

- **Key:** `package-income-{YYYY-MM-DD}` (compatible with production key format).
- **TTL:** `config('deltasupport.package_income.business_date_lock_ttl_seconds')` default **3600** (floor 120). Addresses production risk that a **120s** lock can expire during a long financial cycle and allow a second owner.
- **Acquire for execution:** non-blocking `get()`; `RuntimeException` if contended.
- **Acquire for rule mutation:** sorted unique dates; non-blocking; `ValidationException` on contention; partial acquires released on failure.
- **Release:** `releaseAll()` reverse order; swallows release errors (expired/already released).
- **Order:** **cache locks before DB transaction** — avoids nested-lock deadlock (never hold DB row lock while waiting on cache).

### 4.2 Guarantees vs non-guarantees

| Guarantee (actual mutex) | Non-guarantee (time / process policy) |
|--------------------------|----------------------------------------|
| One exclusive owner of `package-income-{D}` for execution or conflicting rule mutation | Schedule `00:10` alone preventing all races |
| Financial plan+credit cannot interleave with authorize/revoke/capture for locked dates | Dry-run observing rules changing (dry-run holds no lock by design) |
| Failed authorize/capture releases financial locks in `finally` | Lock surviving process crash beyond TTL (TTL must cover worst-case cycle) |
| Sorted multi-date acquire avoids multi-date deadlock | Cross-host safety if cache driver lacks atomic locks |

### 4.3 Mixed rule versions in one run

Non-dry-run financial paths now:

1. Acquire business-date lock  
2. `clearCache()` on level rules  
3. `plan()` then credit under the same lock  
4. Release in `finally`

Authorize cannot obtain the conflicting date lock while that run is active, so a single business-date run cannot observe a mid-run posting-authorization flip for those dates.

### 4.4 Cache driver compatibility

| Environment | Driver | Notes |
|-------------|--------|-------|
| Local `.env` / intended production default | `database` (`CACHE_STORE=database`) | Supports Laravel atomic locks via cache lock table |
| PHPUnit | `array` (forced in `phpunit.xml`) | In-process locks only; sufficient for regression tests |
| Unsupported for cross-process mutex | `null` / drivers without lock support | Must not be used in production for this path |

**TTL note:** Raising default from 120 → 3600 reduces mid-cycle expiry. Ops should still size `PACKAGE_INCOME_BUSINESS_DATE_LOCK_TTL` above worst observed `financial:daily` duration. Expired locks are released safely; a second process may then acquire.

---

## 5. Authorization boundary rechecks

Inside authorize, after cache locks and inside the DB transaction (on `lockForUpdate()` row):

- Published / applicability / effective_from / band+rate validation  
- `assertReadyForAuthorization()` → capture presence, transition=effective for `existing_prospective`, **baseline freshness**, effective-date not retroactive, **schedule cutover window**

So effective date and baseline freshness are re-evaluated inside the protected boundary, not only in a stale pre-lock UI check.

---

## 6. Open-day snapshot consistency

- Open business-day as-of uses the **capture instant** (`businessNow()`), not Asia/Kolkata end-of-day.
- `ReferralLevelEligibility::businessAsOfEnd($date)` returns `now` when `$date === today`, else end-of-day for past dates.
- Capture skips members/`created_at` after the as-of instant → **does not incorrectly include future same-day eligibility**.
- Capture notes and Admin cutover copy state this explicitly.

---

## 7. Misleading authorize guidance corrected

Removed / replaced any implication that operators should **authorize after the effective business date cycle has already succeeded**.

Authoritative wording now (service + Admin posting card):

> Authorize only after last succeeded business date `< effective_from`, and **before** `financial:daily` at schedule time on `effective_from`. **Do not authorize after the effective date cycle has started or succeeded.** Shared `package-income-{date}` locks block authorize/capture during an active financial run.

---

## 8. Preserved Option B / financial invariants

Unchanged by this concurrency patch:

- Option B unlock formula and bands  
- Grandfather `max(proposed, min(snapshot, current_legacy))`  
- Package terms, ROI, commission percentages, wallets  
- Historical accruals (no backfill)  
- Scheduler registration and arming behavior  
- Associate Commission remains disabled  

---

## 9. Regression tests

`tests/Feature/FinancialCutoverConcurrencyTest.php`:

- Authorize blocked while shared financial lock held; succeeds after release  
- Capture blocked while lock held; safe release; succeeds after release  
- Duplicate financial acquire contention  
- Expired/double-release then authorize  
- Failed authorize (missing capture) releases locks so execution can acquire  
- Open-day as-of instant ≠ future EOD  
- Manual dry-run does not hold exclusive lock  
- Lock key + TTL ≥ 3600; database lock store present  

Related suites: `LevelIncomeUnlockGrandfatheringTest`, `CombinedLevelUnlockQualificationTest`.

---

## 10. Verification results (local)

| Check | Result |
|-------|--------|
| `php artisan test --filter="FinancialCutoverConcurrency\|LevelIncomeUnlockGrandfathering\|CombinedLevelUnlockQualification"` | **44 passed** |
| Full `php artisan test` | **766 passed** (7294 assertions) |
| `npm run build` (Vite) | **Passed** (~6.2s) |
| Push / deploy | **Not performed** (per constraint) |
| Authorize posting / enable Associate Commission / recovery | **Not performed** |

---

## 11. GO / NO-GO

### Code deployment — **GO**

Safe to deploy this concurrency patch as application code after normal backup. No production data mutation is required for the lock itself. Confirm production `CACHE_STORE` remains an atomic-lock-capable driver (`database` or `redis`) and TTL covers longest financial cycle.

### Financial posting authorization — **NO-GO**

Do **not** authorize Level Income posting solely because concurrency locks exist. Still required:

1. Explicit BD approval of Option B + applicability  
2. Fresh grandfather baseline (NULL/unknown modes fail closed; recapture as needed)  
3. Safe window: previous business date succeeded; **before** `00:10` Asia/Kolkata on `effective_from`  
4. No active financial run for conflicting dates (shared lock will refuse if one is active)  
5. Associate Commission remains off; no historical recovery from this cutover  

---

## 12. Files touched (this verification)

| File | Role |
|------|------|
| `app/Services/FinancialBusinessDateLock.php` | Shared lock helper |
| `config/deltasupport.php` | `business_date_lock_ttl_seconds` (default 3600) |
| `app/Services/PackageIncomeAccrual.php` | Lock around plan+credit; dry-run unlocked |
| `app/Services/LevelIncomeRuleAdministration.php` | Authorize/revoke acquire shared locks then DB |
| `app/Services/LevelIncomeUnlockGrandfathering.php` | Capture locks; open-day instant; cutover messaging |
| `app/Services/ReferralLevelEligibility.php` | Open-day `businessAsOfEnd` = now |
| `resources/views/admin/finance/commission-settings.blade.php` | Authorize cutover boundary copy |
| `tests/Feature/FinancialCutoverConcurrencyTest.php` | Concurrency regressions |

---

## 13. Residual risks

1. **TTL expiry mid-run** if a cycle exceeds configured TTL — second owner could appear; size TTL from ops metrics.  
2. **Process crash** before `finally` — lock expires at TTL; same residual as Laravel cache locks generally.  
3. **Publish without authorize** still allowed without financial date locks — intentional; live posting unchanged until authorize.  
4. **Dry-run** can still race with authorize for *preview* accuracy — no wallet writes.  
)
