# Phase 2 — Individual Customer & Agent Payment Control

**Mode:** Investigation and implementation planning only.  
**Date:** 2026-11-24  
**Constraints honored:** No financial posting changes, no production migrations, no Level Income rule authorization, no deploy, no payment stop/resume implementation until client approval.

**Related preserved work:** Dynamic Level Income configuration remains local; proposed unlock rules stay unauthorized (`posting_authorized=false`).

---

## 1. Current Customer and Agent architecture

### 1.1 Finding: there is no Agent role or Agent commission engine

Searched models, services, routes, configs, and docs for agent/customer classifications.

| Concept | Exists? | Evidence |
| --- | --- | --- |
| Separate **Agent** account type / role | **No** | `Member` has no `account_type`, `is_agent`, or agent role field |
| **Customer** as a distinct system type | **No** as a typed role | All end users are `members` rows |
| Member account status | **Yes** | `members.status` ∈ `{active, inactive}` (default `active`) |
| Admin users | **Yes** | `users.is_admin` — panel operators, not income recipients |
| Associate Commission (rank chart) | **Yes, dry-run only** | `AssociateCommissionCalculator` / `AssociateCommissionStructure`; `posting_enabled` defaults false |
| Agent commission pipeline that credits wallets | **No** | Do not invent one |

**Interpretation for the client request:** “Customer” maps to **Member**. “Agent” does **not** map to an implemented role. Closest related concept is **Associate Commission** (fixed USDT/day ranks), which is **not** live posting. Phase 2 must treat Agent commission as **N/A / deferred** unless the client defines a new product role.

### 1.2 Member activation vs account status (separate concepts)

| Layer | Meaning today | Gates income? |
| --- | --- | --- |
| `members.status` | Admin toggle active/inactive (`MemberDirectoryController::updateStatus`) | **No** — accrual does not filter on member status |
| Qualifying approved package | Personal “financial activation” for Level Income unlock / recipient eligibility | **Yes** for Level Income recipient eligibility and qualifying directs |
| Package purchase `APPROVED` + `reviewed_at` | Purchase eligible for ROI/Level planning | **Yes** |
| Package flags | `roi_eligible`, `auto_roi_enabled`, `level_commission_eligible` | **Yes** (package-level) |
| Package income snapshots | `PackageIncomeTerm` / plan version on purchase | **Yes** — terms frozen at approval |

Payment stop/resume must stay **orthogonal** to: account status, package status, referral eligibility, and withdrawal permissions.

---

## 2. All applicable payment sources

### 2.1 Live wallet-crediting income (recurring)

Primary path: scheduled `financial:daily` → `RunDailyFinancialCycle` → `PackageIncomeAccrual::runControlled()`.

| Source | Service | Wallet | Idempotency | Global gates |
| --- | --- | --- | --- | --- |
| **Package ROI** | `PackageIncomeAccrual::creditRoi` / `postRoiWallet` | `roi_wallet_amount` + ledger `roi` | `package_roi_accruals.idempotency_key` = `{purchase_id}\|{business_date}` | `daily_financial_cycle_enabled`, `roi_calculation_enabled`, `roi_wallet_crediting_enabled`, automation armed, emergency stop off, Auto ROI / activation gates |
| **Level Income (legacy % chart)** | `PackageIncomeAccrual::creditCommission` / `postCommissionWallet` | `working_wallet_amount` + ledger `working` | `package_level_accruals.idempotency_key` = `{purchase_id}\|{recipient_member_id}\|{business_date}` | Same cycle + `level_calculation_enabled`, `level_wallet_crediting_enabled`; unlock via `ReferralLevelEligibility`; rates via `LevelIncomeRuleResolver` (hard-coded until authorized) |

**Scheduler:** `DailyFinancialScheduleRegistrar` — `financial:daily` daily at configured time (default 00:10 Asia/Kolkata); `financial:recover` hourly; `ranks:evaluate` daily (rank only, not wallet income).

### 2.2 Present but not live wallet income

| Source | Status | Notes |
| --- | --- | --- |
| **Associate Commission** | Dry-run / structure only | `AssociateCommissionCalculator`; posting must remain disabled |
| **Rewards** | Qualification + physical delivery workflow | `rewards:evaluate` / `RewardEligibilityService`; `RewardFulfillmentService` does **not** credit wallets |
| **Legacy `RoiTransaction` / `LevelCommissionTransaction`** | Historical/report tables | Not the live `financial:daily` credit path |
| **Legacy `package-income:accrue`** | Separate path when `package_income.enabled` | Normal production path is `financial:daily` |
| **Demo / local test accruals** | Isolated | Excluded from controlled daily cycle |

### 2.3 Related controls that are not “income payment”

| Control | Purpose |
| --- | --- |
| Withdrawal settings / `withdrawal_requests_enabled` etc. | Member cash-out, not income generation |
| Member transfers | Wallet movement between members |
| Package purchase spend | Debits wallets to buy packages |
| Emergency stop / automation arm | **Global** kill switch for automatic posting |

---

## 3. Exact services requiring integration (when approved)

Integration points for member-specific payment controls (design only):

1. **`PackageIncomeAccrual::planPurchase` / `commissionPlan` / `executeControlledPlan`**  
   - ROI rows: filter by **purchase owner** (`member_id`) payment control.  
   - Level rows: filter by **recipient** (`sponsor` upline `member_id`) payment control.  
   - Optionally clarify source-side behavior (see §8).

2. **`FinancialControls`**  
   - Individual controls must be checked **after** global allow (never bypass emergency stop / arm / calculation/credit flags).

3. **Recovery paths** (same accrual service)  
   - `runStandardHistorical`, `runTourRecovery`, local test accrue — must honor the same member payment gates so recovery cannot bypass a stop.

4. **Admin Member UI**  
   - `MemberDirectoryController`, `MemberController` (active/inactive/update), `CatalogReportController::member` detail surfaces.

5. **New service (proposed)**  
   - e.g. `MemberPaymentControlService` — resolve effective flags for a member + business date; write audit; never credit wallets itself.

**Do not integrate (Phase 2):** Associate posting enablement, inventing Agent engine, Level Income `posting_authorized` activation, withdrawal permission flips as a substitute for income stop.

---

## 4. Proposed database schema (additive, not executed)

Suggested tables (names illustrative; finalize at implementation):

### 4.1 `member_payment_controls` (current state per member)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `member_id` | string, unique | FK logical → `members.member_id` |
| `all_recurring_income_enabled` | boolean, default true | Master switch |
| `roi_enabled` | boolean, default true | |
| `level_income_enabled` | boolean, default true | Recipient-side |
| `agent_commission_enabled` | boolean, default true | Stored for future; **no-op** until Agent/Associate live posting exists |
| `stop_reason` | text nullable | Required when any switch turns off |
| `stopped_at` | datetime nullable | When current stop began |
| `stop_effective_from` | date nullable | First business date with no new credits |
| `resume_effective_from` | date nullable | First business date credits may resume |
| `updated_by` | user id nullable | |
| `timestamps` | | |

**Default:** absent row = all payments allowed (fail-open to current behavior). Safer alternative (fail-closed) is **not** recommended without migration backfill of all members.

### 4.2 `member_payment_control_events` (immutable history)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `member_id` | string | |
| `action` | string | `stop_all`, `resume_all`, `stop_roi`, `resume_roi`, `stop_level`, `resume_level`, … |
| `reason` | text | Mandatory on stop actions |
| `effective_from` | date | |
| `resume_effective_from` | date nullable | |
| `before_json` / `after_json` | json | Flag snapshots |
| `actor_id` | user id | |
| `ip_address` | string nullable | |
| `timestamps` | | |

No changes to ledger, wallets, accruals, or package terms in Phase 2 schema.

---

## 5. Admin UI design (Panel V2 — propose only)

### 5.1 Member Directory (`admin/members`)

- Badge column: **Payments On** / **Stopped** / **Partial** (ROI or Level only).
- Filter: `payment_status=on|stopped|partial`.
- Keep existing status filter (`active`/`inactive`) separate.

### 5.2 Member Details / Update surfaces

Add a **Payment Controls** card:

- Master: All recurring income ON/OFF  
- ROI ON/OFF  
- Level Income ON/OFF  
- Agent commission ON/OFF — disabled/read-only with copy: “No Agent commission posting engine in production”  
- Effective stop date (default: today Asia/Kolkata)  
- Effective resume date (optional on stop; required on resume if using deferred resume)  
- Mandatory reason on any stop  
- Confirmation modal: “This does not change wallet balances, past credits, package duration policy, or account activation.”  
- History table from `member_payment_control_events`

### 5.3 Authorization

- Admin-auth middleware only (same as other admin member routes).  
- No member self-service.  
- Optional: require `is_admin` + confirm checkbox for stop-all.

---

## 6. Stop and resume workflow (proposed)

```
Admin opens Member → Payment Controls
  → Stop (one or more streams) + reason + effective_from
  → Event written; current flags updated
  → financial:daily for business_date >= stop_effective_from skips gated credits
  → Previously accrued/credited rows untouched

Admin Resume + resume_effective_from
  → Event written; flags re-enabled for dates >= resume_effective_from
  → Future eligible days may credit again
  → No automatic backfill of stopped calendar days
```

### 6.1 Effective-date handling

- Business timezone: Asia/Kolkata (same as package income).  
- For business date `D`: payment allowed iff control enabled **and** (`D < stop_effective_from` OR after resume with `D >= resume_effective_from` under the chosen policy).  
- Recommend storing an explicit interval model or “current stop window” to avoid ambiguous reopen logic.  
- Concurrent daily run: resolve control inside the same DB transaction / lock path as credit, using `business_date` not `now()` alone.

### 6.2 Duplicate / history safety

- Existing accrual idempotency keys unchanged.  
- Stop must not delete or rewrite `package_roi_accruals` / `package_level_accruals`.  
- Resume must not re-credit `status=accrued` rows.  
- If global mode left rows at `calculated` without wallet credit, resume + global credit-on may still post those — this is **global** behavior already; member stop should preferably avoid creating new `calculated` rows during a stop if policy is “no calculation” (see §8).

---

## 7. Impact on existing members

| Area | Impact if implemented as designed |
| --- | --- |
| Members with no control row | Unchanged (payments continue) |
| Historical ROI / Level credits | Unchanged |
| Wallet balances / ledger | Unchanged by stop/resume actions |
| Approved purchase snapshots / durations | Unchanged unless client chooses window-extension policy |
| Referral eligibility / unlock counts | Unchanged (eligibility ≠ payment) |
| Account active/inactive | Unchanged |
| Withdrawals | Unchanged (separate controls) |
| Dynamic Level Income drafts | Unaffected; remain unauthorized |

---

## 8. Financial policy decisions requiring client approval

**Do not implement until these are answered in writing.**

### Decision A — Duration consumption (critical)

Accrual windows are **calendar-based**: `firstAccrualDate` … `first + days - 1` (`PackageIncomePolicy::withinWindow` / `lastAccrualDate`). They are **not** “N successful credit days.”

| Option | Behavior during stop | Client must choose |
| --- | --- | --- |
| **A1. Consume (default to current engine math)** | Stopped calendar days inside the window are skipped forever; package end date unchanged | Matches today’s window math; member loses those days’ income |
| **A2. Extend** | Stopped days push `lastAccrualDate` forward (or pause the day counter) | Requires explicit window-extension logic; changes entitlement length |

**Recommendation for proposal text:** Default design assumes **A1** unless client selects **A2**. Do not silently extend durations.

### Decision B — Calculation vs wallet credit only

Global controls already split calculation vs crediting (`roi_calculation_enabled` vs `roi_wallet_crediting_enabled`, same for level). When calculation is on and credit off, rows can be stored as `calculated` and later wallet-posted.

| Option | Behavior | Risk |
| --- | --- | --- |
| **B1. Skip planning/credit entirely while stopped** | No new accrual row for that member/stream/date | Cleanest “no payment”; aligns with “no new scheduled credits” |
| **B2. Calculate but do not credit** | Insert `calculated` rows; credit later on resume | Can look like backfill when credit turns on; conflicts with “no automatic historical backfill” unless resume ignores past `calculated` stop-period rows |

**Recommendation:** **B1** for member-level stop (skip both calculation and credit for gated streams). Mirror client wording: “payments stop.”

### Decision C — Level Income scope

When Level Income is stopped for member M:

| Option | Meaning |
| --- | --- |
| **C1. Recipient only (recommended)** | M does not receive Level Income; M’s purchases can still generate Level Income for uplines |
| **C2. Recipient + source** | Also suppress commission generated from M’s purchases to uplines |

### Decision D — “All recurring income”

Confirm master switch covers **ROI + Level Income only** (live sources). Agent/Associate remains no-op until a live engine exists. Rewards (non-wallet) excluded unless client asks otherwise.

### Decision E — Resume semantics

| Option | Meaning |
| --- | --- |
| **E1. Immediate** | Flag on → next `financial:daily` for today+ may credit if still inside package window |
| **E2. Deferred resume date** | Credits only for `business_date >= resume_effective_from` |
| Either way | **No** automatic credit of stopped dates between stop and resume |

### Decision F — Agent / Associate

Confirm: no Agent payment control behavior until a live Agent/Associate posting product is approved. UI may show a disabled control for future use.

---

## 9. Testing and rollback plan (for future implementation)

### 9.1 Tests (when building)

- Stop ROI only / Level only / all — no new credits; other members unaffected  
- Resume without backfill  
- Idempotency: re-run `financial:daily` after stop/resume  
- Global emergency stop still blocks everything  
- Member `inactive` still independent of payment control  
- Withdrawal still independent  
- Recovery commands honor member stop  
- Associate posting remains disabled  
- Level Income dynamic config remains unauthorized  
- UI: reason required; unauthorized user 403; filter/badge  
- Concurrent control change during daily run

### 9.2 Rollback

1. Feature flag / config `member_payment_controls.enabled=false` → resolver always allows.  
2. Do not drop tables immediately; leave history.  
3. Code revert restores prior accrual paths.  
4. Never reverse ledger as part of rollback.

---

## 10. Implementation sequencing (after approval)

1. Written answers to Decisions A–F.  
2. Additive migrations (local first).  
3. `MemberPaymentControlService` + audit.  
4. Gate in `PackageIncomeAccrual` only (preserve global controls).  
5. Admin Panel V2 UI.  
6. Full PHPUnit + Vite build.  
7. Local dry-run evidence.  
8. **Stop before production migrate / arming** unless separately approved.

---

## 11. Explicit non-actions this phase

- No code changes to posting behavior in this phase  
- No production migrations  
- No authorize of Level Income proposed rules  
- No Associate posting enablement  
- No inventing Agent commission engine  
- No historical income recalculation design as automatic behavior  

**Status:** Awaiting client approval of financial policy decisions (especially **A** duration and **B** calculate-vs-credit) before any stop/resume implementation.
