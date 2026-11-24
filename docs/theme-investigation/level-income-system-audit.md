# Level Income & Level Unlock — Complete Read-Only Audit

**Mode:** Read-only. No code, config, DB, or financial changes.  
**Scope:** Local source tree `c:\xampp\htdocs\delta_support`.  
**Production:** Not assumed identical — env flags and DB package rows can diverge. Confirmed from **local source** only unless noted.

---

## 0. Critical architecture note (two engines)

| Engine | Status in source | Formula | Levels |
| --- | --- | --- | --- |
| **Legacy accrual** (`PackageIncomeAccrual`) | **Live posting path** when financial automation is armed | `commission_pool × rate% / 100` (USDT/day) | 20 levels |
| **Associate Commission** (`AssociateCommissionCalculator` + structure) | **Dry-run only**; `posting_enabled` / `snapshot_on_approval` default **false** | Fixed USDT/day per rank from chart | 20 ranks |

Unlock thresholds (`ReferralLevelEligibility`) are **shared** by both engines.

Legacy `package_income.enabled` is **false**; daily posting uses `financial:daily` (not `package-income:accrue` on the normal path). `PackageLevelCommissionEngine::credit()` always throws — unused for live credits.

---

## 1. Current level structure

### Totals

- **Income levels / ranks:** **20** (`ReferralLevelEligibility::MAX_LEVELS`, `CommissionPlan::RATE_BY_LEVEL`).
- **No per-level “direct requirement” for levels 1–20 individually.** Unlock is **band-based** by qualifying direct **count** (see §2).
- **No team-business requirement** in Level Income unlock (team business belongs to **Rank** system, separate).
- **No personal package amount threshold** for unlock beyond “recipient has a qualifying approved purchase” (recipient eligibility).
- **Duration:** Legacy uses package `commission_days` (400 for configured codes). Associate chart is **400 days** for all mapped tiers.

### Level-by-level rate table (legacy % chart — source of truth for live accrual)

Defined in `CommissionPlan::RATE_BY_LEVEL` (`app/Services/CommissionPlan.php` L15–36). Stored DB rows must match and must stay **inactive** (`isActive()` / integrity check).

| Level | Rate % of daily pool | Directs needed to unlock this level* | Notes |
| --- | --- | --- | --- |
| 1 | 25.0000 | ≥1 qualifying direct (unlocks 1–4) | |
| 2 | 12.5000 | ≥1 | |
| 3 | 7.5000 | ≥1 | |
| 4 | 5.0000 | ≥1 | |
| 5–8 | 2.5000 each | ≥2 (unlocks 1–8) | |
| 9–12 | 2.5000 each | ≥3 (unlocks 1–12) | |
| 13–16 | 2.5000 each | ≥4 (unlocks 1–16) | |
| 17 | 2.5000 | ≥5 (unlocks 1–20) | |
| 18 | 5.0000 | ≥5 | |
| 19 | 5.0000 | ≥5 | |
| 20 | 7.5000 | ≥5 | |
| **Sum** | **100.0000%** | | |

\*Unlock is not “N directs for level N”; see `levelsForCount()` bands.

### Unlock bands (qualifying directs → unlocked levels)

From `ReferralLevelEligibility::levelsForCount()` L156–165:

| Qualifying directs | Unlocked levels |
| --- | --- |
| 0 | 0 |
| 1 | 4 |
| 2 | 8 |
| 3 | 12 |
| 4 | 16 |
| ≥5 | 20 |

### What counts as a qualifying direct

`ReferralLevelEligibility` L18–22, L133–154:

- Direct = `members.sponsor_id = upline` and not self.
- Qualifying = that direct has **≥1 approved** `package_purchases` where:
  - `package_type` ∈ `{tour_roi, premium, roi}` **OR**
  - `package_code` ∈ keys of `config('deltasupport.package_income.packages')` **and** catalog package has `level_commission_eligible = true`.

Tour-only (non-ROI) types are explicitly excluded from `QUALIFYING_PACKAGE_TYPES`.

Member UI copy says “Tour + ROI or Premium” — slightly incomplete vs code (also includes `TYPE_ROI`).

### Personal / team / other conditions

| Condition | Used for Level Income? |
| --- | --- |
| Personal package price tiers | No (for unlock) |
| Team business / ranks | No (separate Rank feature) |
| Member active/inactive status | **Not checked** on recipient (documented unresolved in Associate calculator) |
| Package expiry date field | **Not used** in accrual window; window is **approval + wait_days + commission_days** |
| Admin root `ST666666` | Never receives commission; stops chain |

---

## 2. Level unlock mechanism

### When Level 1 unlocks

When the upline has **≥1 qualifying direct**, `unlocked_levels = 4`, so levels **1–4** are unlocked together — not Level 1 alone.

Evidence: `levelsForCount` L158–164; pay gate `$unlocked >= $level` in `PackageIncomeAccrual::commissionPlan` L644–646.

### Subsequent levels

Same live evaluation: as qualifying direct count rises, unlocked cap jumps 4→8→12→16→20. Automatic; **no admin “manual unlock” API** found.

### Automatic vs manual

- **Automatic** at calculation time via `ReferralLevelEligibility::forMember()`.
- **Not frozen** at purchase approval for legacy accrual (Associate dry-run notes the same: “evaluated live”).

### Approved package mandatory?

- For a **direct to count toward unlock:** yes — approved purchase meeting types/codes above.
- For a **recipient to receive pay:** also must pass `recipientEligible()` = has own qualifying purchase (`PackageIncomeAccrual` L680–691).

### Package expiry

Accrual uses `firstAccrualDate = approval + wait_days` (default **10**) then `commission_days` inclusive window (`PackageIncomePolicy` L85–115). No separate “package expired” stop flag found in this path.

### Can unlocked levels lock again?

Yes, effectively: if qualifying directs drop below a band (e.g. cancelled purchase no longer counts — only **approved** rows qualify), live `unlocked_levels` decreases. Historical accrued rows remain; future days would skip newly locked ranks.

### Existing vs future members

Same code path for all members. No separate grandfathering rule in unlock service. Purchase **income terms / snapshots** can preserve ROI/commission **amounts and days** for accrual definitions (`definitionForPurchase`), but unlock counts remain live.

---

## 3. Level Income calculation (legacy live path)

### Formula

```text
daily_level_amount = commission_pool × RATE_BY_LEVEL[level] / 100
```

Implementation: `PackageIncomePolicy::levelAmount()` L117–125 — `bcmul` then `bcdiv` at **4 decimal places**.

### Base amount

- **Not** package purchase price.
- **Not** daily ROI amount.
- **Yes:** package definition `commission_pool` (USDT/day theoretical pool across ranks 1–20).
- Associate engine (inactive posting): fixed rank amounts (equal to pool×% for Gold chart; independent fixed chart for Silver/Premium/VIP).

### Local `package_income.packages` (source config)

| Code | Local name mapping (associate / comments) | ROI daily | ROI days | Commission pool | Commission days |
| --- | --- | --- | --- | --- | --- |
| DST-ROI-002 | GOLD (associate map) | 0.2500 | 800 | **0.2400** | **400** |
| DST-TOUR-003 | PREMIUM (associate map) | 2.5000 | 1000 | **2.4000** | **400** (`$vipCommissionDays`) |
| DEMO-COAST | demo | 0.2500 | 800 | 0.2400 | 400 |
| DEMO-HILLS | demo | 2.5000 | 1000 | 2.4000 | 400 |
| DST-TOUR-001 | SILVER (associate only) | *(not in package_income.packages)* | — | legacy pool **null** | — |
| DST-TOUR-004 | VIP (associate only) | *(not in package_income.packages)* | — | legacy pool **null** | — |
| DIAMOND | **Not defined** in associate tiers or `package_income.packages` | — | — | — | — |

`definition_source_policy`: `legacy_config_preferred` — config codes stay authoritative until packages explicitly switch to DB.

### Worked examples (legacy pool × %, 4dp)

#### GOLD / DST-ROI-002 — pool **0.2400** USDT/day

| L | Rate % | Daily USDT |
| --- | --- | --- |
| 1 | 25.0000 | **0.0600** |
| 2 | 12.5000 | **0.0300** |
| 3 | 7.5000 | **0.0180** |
| 4 | 5.0000 | **0.0120** |
| 5–17 | 2.5000 | **0.0060** each |
| 18–19 | 5.0000 | **0.0120** each |
| 20 | 7.5000 | **0.0180** |
| **Total** | 100% | **0.2400** |

Over **400** days (if always paid): L1 max **24.0000**; full pool × 400 = **96.0000** USDT theoretical.

#### PREMIUM / DST-TOUR-003 — pool **2.4000** USDT/day

| L | Daily USDT |
| --- | --- |
| 1 | **0.6000** |
| 2 | **0.3000** |
| 3 | **0.1800** |
| 4 | **0.1200** |
| 5–17 | **0.0600** each |
| 18–19 | **0.1200** each |
| 20 | **0.1800** |
| **Total** | **2.4000** |

Duration total theoretical: **960.0000** USDT over 400 days.

#### SILVER / DST-TOUR-001 — Associate chart only (posting off)

Fixed ranks in `AssociateCommissionStructure::TIERS['silver']` L28–40: daily total **0.1200**, duration total **48.0000**.  
**No** legacy `commission_pool` in `package_income.packages` → legacy accrual **does not** plan commission for this code unless DB definition adds pool + days + `level_commission_eligible`.

#### VIP / DST-TOUR-004 — Associate chart only (posting off)

Fixed ranks L70–82: daily total **2.4000**, duration **960.0000**. Same as Premium **pool size**, but VIP is **not** in legacy `package_income.packages` for ROI/commission accrual.

#### DIAMOND

No Associate tier and no `package_income.packages` entry. `original_tour_plan` proposes DST-TOUR-005 ROI only (`commission_pool` null) — **proposed / inactive**, not live Level Income.

### Payable vs theoretical

Only sponsors who are **recipient-eligible** and have `unlocked_levels >= level` receive a planned line. Remaining pool for skipped ranks is **not** redistributed (Associate dry-run: `unallocated_pool`; legacy: skip message only).

---

## 4. Locked level income

When `$unlocked < $level` or recipient ineligible (`PackageIncomeAccrual` L646–675):

| Behavior | Verdict |
| --- | --- |
| Skipped for that day / level | **Yes** — `"… was not paid at level N."` |
| Permanently forfeited for that business date | **Yes** for that date’s cycle (no hold row) |
| Held as pending until unlock | **No** |
| Spillover / transfer to another upline | **No** |
| Released after future qualification for **past** dates | **No automatic** — past skipped dates are not retro-created by unlock alone |
| Future dates after unlock | **Yes** — live unlock evaluation pays subsequent days in window |

Idempotency: existing `package_level_accruals` with status `accrued` for same purchase+member+date blocks re-plan (`L647–653`).

Historical recovery commands exist for controlled missed dates (`runStandardHistorical` / Tour recovery) — **separate explicit ops**, not “auto backfill on unlock”. Tour/Standard recovery packages are tightly gated in config.

---

## 5. Income duration

| Rule | Source |
| --- | --- |
| Start | `approval_date + wait_days` (default **10**, Asia/Kolkata) — `PackageIncomePolicy::firstAccrualDate` L85–96 |
| Commission continues | `commission_days` inclusive from first day — L98–115 |
| ROI duration | Separate `roi_days` (800 / 1000 in config) — **can differ** from commission days |
| Depends on source member package | Yes — definition / snapshot on **source purchase** |
| Package expiry / inactivity | Not a separate stop; window + eligibility gates apply. Member inactive **not** checked |
| Snapshots | `definitionForPurchase` / `PackageIncomeTerm` can preserve historical terms; Associate snapshot flags default **off** |

Tour packages may also require activation floor + `tour_posting_authorized` before forward posting (`PackageIncomeAccrual::planPurchase`).

---

## 6. Admin panel controls

| Capability | Implemented? | Where |
| --- | --- | --- |
| View legacy % chart | Yes (read-only display) | `admin.finance.commission-settings` |
| Change level percentages in UI | **No** (chart integrity requires fixed rates; plan must stay inactive) | `CommissionPlan` |
| View Associate fixed ranks | Yes (read-only) | same + ROI plans columns |
| Change direct unlock bands in UI | **No** | Hard-coded `levelsForCount` |
| Enable/disable individual levels | **No** UI toggle for pay levels | |
| Manually unlock levels | **No** | |
| Change qualification types in UI | **No** | Constants in `ReferralLevelEligibility` |
| Package-specific commission rules | Config / Associate structure / package flags; UI mostly **display** | `roi-plans`, commission-settings |
| View member unlocked/locked | Eligibility list | `admin.finance.eligibility` |
| View skip reasons in ops UI | Partial — Income Control / previews show pending lines & labels; not a dedicated “skip reason ledger” | `roi-schedule`, demos |
| Commission history | Yes | `admin.finance.level-income`, reports |
| Arm / calculation / wallet credit flags | Yes (Financial Controls / Income Control) | `level_calculation_enabled`, `level_wallet_crediting_enabled` |

Unlock rules page: `admin.finance.level-unlock-rules` → view `admin.finance.level-rules` (read-only table + qualifying types).

---

## 7. Member panel

| Shown | Page | Notes |
| --- | --- | --- |
| Unlocked levels / status | `member/eligibility` | Explicit: “Commission is not being credited.” |
| Unlock band copy | same | 1→4 … 5→20 |
| Qualifying directs list | same | |
| Locked levels list | Partial (count only, not per-level lock UI) | |
| Level-wise % chart | Not on eligibility; Associate/legacy charts are admin | |
| Remaining to next band | **Not** computed in eligibility view | |
| Daily / total Level Income | Wallet / income history / reports | Depends on posted accruals |
| Level-wise history | `member` reports level-income + income history routes | |

**Misleading risk:** Rank pages talk about “32 levels” (rank unlocks) — **different system** from 20-level commission. Member eligibility correctly says 20.

---

## 8. Financial engine & safety (pipeline)

```text
Scheduler / ops
  → financial:daily (preferred)  [or legacy package-income:accrue when enabled]
  → PackageIncomeAccrual plan/accrue
       → PackageIncomeDefinitionResolver (purchase snapshot / config)
       → PackageIncomePolicy windows + levelAmount
       → ReferralLevelEligibility unlock + recipientEligible
       → Upline walk (sponsor_id chain)
       → package_level_accruals (idempotency_key)
       → Working wallet credit when level_wallet_crediting_enabled
       → Ledger via wallet poster (wallet_ledger.enabled currently false in config — verify runtime)
FinancialControls: level_calculation_enabled, level_wallet_crediting_enabled, arm, emergency stop
```

**Config change impact on existing purchases:**  
Changing `CommissionPlan::RATE_BY_LEVEL` or unlock bands affects **future** calculation immediately (live). Purchase snapshots protect **definition pool/days/ROI** when present; they do **not** freeze unlock counts. Enabling Associate posting **alongside** legacy risks double pay on GOLD/PREMIUM (calculator warns explicitly).

**This audit did not execute** `financial:daily` or any posting command.

---

## 9. Final report sections

### 9.1 Current rules (summary)

- 20 levels; band unlock by qualifying directs 0/1/2/3/4/5+ → 0/4/8/12/16/20.
- Live pay = pool × % / 100 for packages with commission definition + eligible flag.
- Associate fixed chart exists but posting disabled in source defaults.
- Skipped locked ranks: forfeit that day; no spillover; no auto retro on unlock.
- ROI days ≠ commission days for configured packages.

### 9.2–9.4 Tables & examples

See §§1–3 above.

### 9.5 Unlock flow

Direct gets approved qualifying package → upline’s qualifying count increments → unlocked band expands → next accrual dates pay newly unlocked levels if still in commission window.

### 9.6 Locked income

Skip / forfeit for that business date; continue walking upline for higher levels with their own eligibility.

### 9.7 Duration

wait_days (10) then commission_days (400 for configured pools).

### 9.8–9.9 Admin / Member

See §§6–7. Mostly **read-only** policy displays; real switches are financial arming flags and package catalog eligibility — not editable commission percentages in UI.

### 9.10 Bugs / inconsistencies

1. **Dual engine risk** if Associate posting ever enabled without turning off legacy pool accrual (GOLD/PREMIUM overlap).
2. Member eligibility copy omits `TYPE_ROI` wording.
3. **Dual unlock UIs:** Accrual uses **ReferralLevelEligibility (20)**; member Level Commission Report + Rank pages use **RankService (up to 32)** and claim locked levels are “not eligible for Level Commission” against the wrong model (`Member\ReportController` L73–77; `member/reports/level-income.blade.php`).
4. **Dual history sources:** Sidebar Level Income History unions `level_commission_transactions` + `package_level_accruals` (`FinancialHistoryQuery`); orphan report routes query **legacy transactions only** — totals can disagree.
5. SILVER/VIP associate mapping without legacy `commission_pool` → Associate-only until DB/config adds pools.
6. No DIAMOND Level Income definition in live associate/legacy maps.
7. `CommissionPlan` stored rows must remain inactive; if someone activates plan rows, integrityErrors fire — accrual requires `ratesReady` (integrity empty).
8. **Purchase-term snapshot gap:** if `PackageIncomeTerm` leaves `commission_days` null and no plan version fills it, `levelSourceEligible` can block commission for that purchase.
9. History badge always **“Recorded”** — does not show calculated-vs-wallet-credited.
10. Commission settings table header “Unlocked ranks” shows referral **level counts** (wording mismatch).
11. Orphan routes (no sidebar): `admin.report.level-income`, `member.reports.level-income`, `member.rank`.

### 9.11 Financial risks

- Live unlock changes rewrite who gets paid mid-stream without freezing at approval.
- Historical recovery can credit missed dates if ops run recovery — unlock alone does not.
- Wallet ledger globally disabled in config default — confirm production `.env` separately.
- Tour posting authorization separate from Standard.
- PREMIUM/`DST-TOUR-003` still carries legacy ROI **2.5000×1000** in `package_income` while Associate maps Premium daily total **1.2000** — ROI vs commission product mismatch if both interpreted as “Premium.”
- Financial settings copy can say commission “posts on schedule” while `level_wallet_crediting_enabled` defaults off until armed.

### 9.12 Improvement opportunities (recommendations only — not implemented)

- Single engine decision (legacy % vs Associate fixed) with exclusivity flag.
- Freeze unlock snapshot at purchase or accrual day start if business requires.
- Explicit hold/forfeit policy UI and skip-reason ledger.
- Member “next band” progress + per-level lock/unlock matrix.
- Align SILVER/VIP/DIAMOND definitions across catalog, associate, and package_income.
- Admin editable unlock bands only behind migration + snapshot policy.

### 9.13 Exact controlling files

| Concern | File | Lines (approx.) |
| --- | --- | --- |
| Unlock bands / qualifying types | `app/Services/ReferralLevelEligibility.php` | 11–22, 133–165 |
| Legacy % rates | `app/Services/CommissionPlan.php` | 15–36, 46–78 |
| Pool × rate | `app/Services/PackageIncomePolicy.php` | 51–61, 85–125 |
| Plan + skip + upline | `app/Services/PackageIncomeAccrual.php` | 536–691, credit ~780+ |
| Associate fixed chart | `app/Services/AssociateCommissionStructure.php` | 27–84, 90–93 |
| Associate dry-run | `app/Services/AssociateCommissionCalculator.php` | 35–161, 216–279 |
| Package pools (local) | `config/deltasupport.php` | 53–120, 138–156 |
| Stub engine | `app/Services/PackageLevelCommissionEngine.php` | 11–29 |
| Accrue command | `app/Console/Commands/AccruePackageIncome.php` | 15–54 |
| Daily financial | `app/Console/Commands/RunDailyFinancialCycle.php` | signature / handle |
| Admin commission UI | `app/Http/Controllers/Admin/OperationsController.php` | 180–235 |
| Admin views | `resources/views/admin/finance/commission-settings.blade.php`, `level-rules.blade.php` | |
| Member eligibility | `resources/views/member/finance/eligibility.blade.php` | 1–25 |
| Controls | `app/Services/FinancialControls.php` | level_* flags |

---

## Local vs production

| Item | Local source default | Production |
| --- | --- | --- |
| `package_income.enabled` | `false` | Verify env / arming |
| Associate `posting_enabled` | `false` | Verify `DELTASUPPORT_ASSOCIATE_COMMISSION_*` |
| `tour_posting_authorized` | env default false | Verify |
| `wallet_ledger.enabled` | `false` | Verify |
| Package catalog rows / `level_commission_eligible` | DB-dependent | Must match intended codes |
| Tier code maps | env overrides possible | Verify `DELTASUPPORT_ASSOC_CODE_*` |

**Do not assume local ≡ production.**

---

## Addendum — UI route inventory (from UI audit)

| Surface | Route | Editable? | Unlock model shown |
| --- | --- | --- | --- |
| Commission settings | `admin.finance.commission-settings` | No | 20 (bands) + Associate ranks |
| Level unlock rules | `admin.finance.level-unlock-rules` | No | 20 |
| Level income history | `admin.finance.level-income` | Search only | N/A (posted rows) |
| Referral eligibility | `admin.finance.eligibility` / `member.eligibility` | No | 20 |
| Member level history | `member.income.level` | Search only | N/A |
| Level commission report | `admin.report.level-income` / `member.reports.level-income` | Filters/export | Member report chips: **32 (rank)** |
| Income Control / Real Income | `admin.finance.roi-schedule` / `real-income` | No | Skip reasons / pending lines |
| Demo income preview | `admin.demo-income` | No | Preview only |
| Rank admin / member rank | `admin.ranks.*` / `member.rank` | Admin rank CRUD | **32** |

Skipped reasons for members: **not** shown. Skip/pending detail is admin Income Control / Real Income / income-test.

Sources merged from engine audit [Level Income / Level Unlock](08014eba-a995-4bce-90e1-6f76b8d33bb2) and UI audit [Admin & Member Level Income UI](fea6065f-1c34-43ad-9e30-b387fc464b5c).

---

## Stop point

Audit complete. **No implementation** of new business rules until you provide requirements.
