# MariaDB DatabaseLock Same-Second Refresh Hotfix

**Project:** Delta Support — financial runtime lock safety  
**Date:** 2026-11-27 (updated: fail-closed retry + near-full TTL lease)  
**Production commit exhibiting defect:** `96b8062d`  
**Urgency:** Deployed naive `refreshOwned()` can abort `financial:daily` at 00:10 Asia/Kolkata before any ROI / Level Income credit when MariaDB reports a same-second refresh no-op as failure.

**Constraints honored:** No ROI/commission/formula/wallet/history changes; no posting authorization; no scheduler/control changes; no production deploy or financial execution; minimal hotfix only.

---

## 1. Production evidence

| Step | Result |
|------|--------|
| Acquire TTL 60 → immediate `refresh(60)` | **false** |
| Acquire TTL 60 → immediate `refresh(120)` | **true** |
| Acquire TTL 60 → `sleep(2)` → `refresh(60)` | **true** |

Laravel `DatabaseLock::refresh()` returns whether an owner-scoped, unexpired-row `UPDATE` affected ≥1 row. On MariaDB, when the new `expiration` unix timestamp equals the existing value (same second + same TTL), **zero rows are reported changed** even though the lock is still valid.

---

## 2. Root cause

```php
// Illuminate\Cache\DatabaseLock::refresh()
->update(['expiration' => $this->expiresAt($seconds)]) >= 1;
```

`expiresAt($seconds) = currentTime() + $seconds`. Immediate refresh with the same TTL recomputes the same integer expiration → MariaDB no-op → `false`.

Treating every `false` as ownership loss aborts financial execution incorrectly.

---

## 3. Fix (current)

File: `app/Services/FinancialBusinessDateLock.php`

### Refresh algorithm

1. `refresh(ttl)` — success → continue.  
2. Exceptions from refresh are **never swallowed** (wrapped `RuntimeException`, fail closed).  
3. On `false` → `assertDatabaseLockOwnedWithNearFullLease()`:  
   - Must be `DatabaseLock`  
   - Read row via **the lock’s own connection + table + prefixed storage key**  
   - Query uses **`useWritePdo()`** (primary / write connection, not a read replica)  
   - Owner token must match `$lock->owner()`  
   - `expiration > now`  
   - Remaining lease must be **≥ TTL − 2 seconds** (near-full TTL; e.g. TTL 3600 requires ≥ 3598 remaining — **60s is not enough**)  
4. `refresh(ttl + 1)` to force a distinct expiration when possible.  
5. Retry exceptions are **not swallowed**.  
6. If retry returns `false` → **re-verify** ownership / expiry / near-full lease before continuing.  
7. Non-database drivers: `false` remains fail-closed.

### Removed / simplified

- No sleep-based workarounds  
- No vendor edits  
- Dropped unused config-prefix helpers; meta comes from the live `DatabaseLock` instance (connection, table, prefixed key, owner)

---

## 4. Guarantees after hotfix

| Accepted | Rejected (fail closed) |
|----------|-------------------------|
| `refresh() === true` | Expired row |
| Same-second no-op **and** retry false, only after primary-write verify with **near-full TTL** remaining | Stolen owner |
| | Missing lock row |
| | Remaining lease far below configured TTL (e.g. 60s left on 3600s TTL) |
| | DB / refresh exceptions (initial or retry) |
| | Non-database `refresh() === false` |

Still **not** absolute mutual exclusion mid-chunk / crash-before-finally (unchanged residual).

---

## 5. Regression tests

`tests/Feature/MariaDbLockRefreshHotfixTest.php`:

- Repeated same-second `refreshOwned` succeeds  
- Ownership loss between initial verification and retry rejected  
- Retry returning false still re-verifies (passes when still owned with near-full lease)  
- Retry throwing DB exception is not swallowed  
- Insufficient remaining lease rejected (60s vs 3600 TTL)  
- Custom lock table + cache prefix honored from the live lock  
- Global + business-date locks both verified  
- Expired / stolen / missing fail closed  
- Array driver false refresh fails closed  

---

## 6. Verification

| Check | Result |
|-------|--------|
| Targeted MariaDB / runtime / concurrency tests | Passed |
| Full `php artisan test` | **784 passed** (7385 assertions) |
| `git diff --check` (unstaged + staged) | **Clean** (exit 0) |
| Production deploy / financial:daily | **Not executed** |

---

## 7. Deployment note (ops)

Deploy this hotfix **before** the next `00:10` Asia/Kolkata `financial:daily` if `96b8062d` (or equivalent fail-closed-on-false refresh) is live. Confirm `CACHE_STORE=database` and lock table connectivity on the **primary** writer.

| Gate | Verdict |
|------|---------|
| **Code deployment (hotfix)** | **GO** (urgent) |
| **Financial posting authorization** | **NO-GO** (unchanged) |
