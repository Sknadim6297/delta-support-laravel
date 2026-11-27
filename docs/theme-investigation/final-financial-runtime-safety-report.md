# Final Financial Runtime Safety Report

**Project:** Delta Support — financial concurrency + historical rule-resolution safety  
**Date:** 2026-11-27 (updated: accruePurchases refresh follow-up)  
**Production commit under review:** `cdba5b70`  
**Local constraint:** No deploy, push, authorize posting, Associate Commission enablement, scheduler/control flag changes, production financial commands, or historical recovery execution.

---

## 1. Executive summary

| Gate | Verdict |
|------|---------|
| **Code deployment** | **GO** (after backup; confirm `CACHE_STORE` supports atomic locks) |
| **Financial posting authorization** | **NO-GO** (Option B published+unauthorized is correct until BD cutover window + fresh baseline) |

Production already had per-date `package-income-{date}` locks from the concurrency patch. This audit confirmed additional defects and residual risks, then applied **minimal** fixes only.

**Important:** These locks provide **best-effort mutual exclusion with stale-writer abort**, not absolute exclusion for the entire lifetime of a lock without refresh. See §6 and §8.

---

## 2. Confirmed defects (with source evidence)

### D1 — Publish could silently drop live authorized rules to legacy

**Evidence (pre-fix):** `LevelIncomeRuleAdministration::publish()` superseded all other published rows and set the new row `posting_authorized=false` without checking whether a live authorized version existed. `LevelIncomeRuleResolver::authorizedVersion()` requires `status=published AND posting_authorized=true`, so superseding the live row removed it from resolution → **authorized → legacy** without revoke/authorize.

**Fix:** Refuse publish while any other version has `posting_authorized=true` (explicit revoke required first). Admin flash copy updated.

### D2 — 3600s TTL could expire mid-run and allow overlapping writes

**Evidence:** `FinancialBusinessDateLock::make()` sets a fixed TTL; Laravel `DatabaseLock::acquire()` steals when `expiration <= now()`. Accrual did not call `refresh()`.

**Fix:** `refreshOwned()` before plan, **again after plan before the first wallet write**, and at the start of each credit chunk for ROI and commission loops (`executeControlledPlan`, `accrue`, `accruePurchases`). Abort with `RuntimeException` if refresh fails (ownership lost / steal).

### D3 — Cross-date financial runs vs authorize (per-date locks only)

**Evidence:** Authorize locked only `{today, effective_from, transition}`. Historical recovery on date `D` outside that set could run while authorize flipped posting rules; sticky resolver cache helps one process but not the race at plan start.

**Fix:** Global exclusive guard `level-income-posting-rules` acquired **before** date locks by both financial execution and authorize/revoke/capture. Order: guard → sorted dates (deadlock-safe).

### D4 — `future_purchases_only` missing business-date ≥ effective gate

**Evidence:** Resolver gated only on purchase approval vs `effective_from`. `existing_prospective` already required `$business >= $effective`. A purchase approved after effective could still resolve AUTHORIZED for a pre-effective business date.

**Fix:** Add business-date gate for `future_purchases_only` (parity; preserves no-backfill semantics).

### D5 — `accruePurchases()` refreshed inconsistently (follow-up)

**Evidence:** Local-test path refreshed before plan and once before commissions, but **not** after plan before the first ROI credit, and ROI/commission loops were not chunk-refreshed like `accrue` / `executeControlledPlan`.

**Fix:** Align `accruePurchases()` with the same post-plan + chunk refresh pattern. Callers of `acquireForExecution()` already expect `list<Lock>` (`PackageIncomeAccrual` + concurrency/runtime tests).

---

## 3. Already safe (no code change)

| Area | Evidence |
|------|----------|
| Same-date plan+credit vs authorize/capture (while locks held and refreshed) | Shared `package-income-{date}` + global rule guard + tests |
| Mid-run mixed versions (single process) | Sticky `authorizedCache` after first load; plan bakes rates into rows before credit |
| Grandfather `asOfInstant` consistency | Computed once before txn; closed over every chunk |
| Resolver `clearCache()` vs financial locks | In-memory only; does not touch `cache_locks` |
| `Cache::store('database')->flush()` vs locks | Deletes value `cache` table only; locks remain in `cache_locks` |
| Accrual double-write on retry | `package_roi_accruals` / `package_level_accruals` idempotency keys |

---

## 4. Historical rule resolution (authorize / revoke)

**Model:** There is **no** point-in-time version history. `authorizedVersion()` loads the **current** published+authorized row.

| Event | Runtime effect |
|-------|----------------|
| Authorize version B (after revoke A) | Future plans use B’s `effective_from` / applicability |
| Revoke | Falls back to hard-coded legacy |
| Re-credit already posted days | Idempotency prevents duplicate wallet rows |
| First-time credit of a past unposted day after later authorize | Uses **current** authorized rules (not “what was live then”) |

**Policy (unchanged, documented):** No historical backfill. Re-processing past dates to “fix” amounts under new rules is unsupported. Full as-of version ledger would be a larger feature and was **not** implemented.

---

## 5. Fixes implemented (files)

| File | Change |
|------|--------|
| `app/Services/FinancialBusinessDateLock.php` | Global rule guard; execution returns lock list; `refreshOwned()` |
| `app/Services/PackageIncomeAccrual.php` | Guard+date; freeze authorized snapshot; refresh after plan + per chunk (incl. `accruePurchases`) |
| `app/Services/LevelIncomeRuleAdministration.php` | Block publish while another version is posting-authorized |
| `app/Services/LevelIncomeRuleResolver.php` | `future_purchases_only` business-date ≥ effective |
| `app/Http/Controllers/Admin/LevelIncomeRuleController.php` | Accurate publish flash |
| `tests/Feature/FinancialRuntimeSafetyTest.php` | DB lock steal/flush + ownership loss before first ROI |
| `tests/Feature/FinancialCutoverConcurrencyTest.php` | Cross-date rule-guard coverage |
| `tests/Feature/LevelIncomeApplicabilityTest.php` | Revoke-before-publish workflow + business-date gate |

**Not changed:** Option B formula, grandfather formula, package ROI/commission rates, wallets, scheduler registration, financial control flags, Associate Commission.

---

## 6. Guarantees (accurate) vs non-guarantees

### What is guaranteed

| Guarantee | Mechanism |
|-----------|-----------|
| Contending acquire fails while owner holds unexpired locks | Laravel cache lock `get()` / DatabaseLock ownership token |
| Financial execution and authorize/revoke/capture cannot both hold the rule guard at once | `level-income-posting-rules` |
| Same business-date writers cannot both hold `package-income-{date}` while unexpired | Per-date lock |
| Accrual aborts before continuing credits if chunk/post-plan `refreshOwned()` fails | Stale-writer protection |
| Publish cannot supersede a still-authorized live version | Explicit validation refuse |

### What is **not** absolute mutual exclusion

| Limitation | Why |
|------------|-----|
| **Inside a credit chunk** | Refresh runs at chunk boundaries, not per row. A single chunk that outlives remaining TTL can still lose ownership mid-chunk before the next refresh. |
| **Long-running rule mutations** | Authorize/revoke/capture acquire locks but **do not** call `refreshOwned()` during work (e.g. large grandfather capture chunks). TTL expiry can allow a later financial acquire to steal. |
| **Process crash before `finally`** | Lock remains until TTL; another process may acquire after expiry. |
| **Dry-run / preview** | No exclusive locks (read-only). |
| **Time-based cutover window (`00:10`)** | Ops/policy guard only — not a process mutex. |

**Do not claim** “absolute mutual exclusion for the entire financial run or entire capture” unless every long path refreshes continuously and every mid-chunk window is shorter than TTL. Current design: **mutex while owned + abort on detected steal at refresh points**.

---

## 7. Test results

| Suite | Result |
|-------|--------|
| Targeted concurrency + runtime + applicability | Passed |
| Full `php artisan test` | **775 passed** (7334 assertions) |
| `git diff --check` | **Clean** (exit 0) |
| Prior `npm run build` | Passed (~4.7s; assets unchanged by this follow-up) |

Coverage includes:

- Publish refused while authorized (live rules preserved)
- Cross-date financial run blocks authorize via rule guard
- `future_purchases_only` legacy before effective business date
- `refreshOwned` / lost ownership abort
- **Ownership loss after plan / before first ROI credit aborts with zero wallet writes**
- `acquireForExecution()` returns a 2-lock array (rule guard + date)
- Database-backed: value flush does not drop locks; expired lock steal detectable via failed refresh
- Resolver `clearCache` does not remove `cache_locks` rows

**Note on “cross-process”:** PHPUnit uses sqlite `:memory:`. Database lock tests exercise real `cache_locks` steal/flush semantics with two lock owner tokens in-process (same SQL engine Laravel uses in production with `CACHE_STORE=database`). True multi-OS-process sharing requires a shared DB; semantics match.

---

## 8. Remaining risks

1. **Mid-chunk / mutation TTL windows** — see §6; not absolute exclusion.  
2. **Process crash before `finally`** — relies on TTL.  
3. **No point-in-time historical rule ledger** — unposted past dates after a later authorize use current rules.  
4. **Global rule guard serializes financial dates** — multi-date recovery queues behind the guard.  
5. **Publish without authorize during legacy-only mode** — allowed; live posting unchanged until authorize.  
6. **Dry-run** — unlocked preview may race with authorize for display accuracy only.

---

## 9. Deployment requirements (code only)

1. Deploy application code with normal backup.  
2. Confirm production `CACHE_STORE=database` (or redis) with working `cache_locks`.  
3. Keep `PACKAGE_INCOME_BUSINESS_DATE_LOCK_TTL` ≥ worst-case chunk duration (default 3600; refresh extends while owned at chunk boundaries).  
4. Do **not** authorize posting as part of this deploy.  
5. Operator workflow: **revoke → publish → (capture) → authorize** when replacing a live authorized version.  
6. For very large grandfather captures, prefer running when no financial cycle is due; mutation paths do not refresh locks today.

---

## 10. GO / NO-GO

### Code deployment — **GO**

Safe for code rollout. Existing financial automation continues; Option B remains published+unauthorized until explicit authorize.

### Financial posting authorization — **NO-GO**

Still requires BD approval, safe Asia/Kolkata window, fresh grandfather baseline (NULL modes fail closed), and explicit authorize. This patch does not authorize and must not be treated as cutover completion.
