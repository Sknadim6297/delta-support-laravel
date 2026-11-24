# Level Unlock & Total Business Fix Report

**Project:** Delta Support (`C:\xampp\htdocs\delta_support`)  
**Production path (ops):** `/home/wwwzeont/deltasupport.online`  
**Date:** 2026-11-24  
**Scope:** Local root-cause fix + release prep. **No production deploy, no financial posting, no live record mutation.**

---

## Verdict

| Item | Recommendation |
|------|----------------|
| **Deploy Tour Only → Total Business (BV) fix** | **GO** (non-income; uses approved purchase snapshots) |
| **Deploy unlock clarity (registered vs qualifying + rule mode UI)** | **GO** |
| **Authorize `proposed_activation_unlock_v1` (0 directs → levels 1–4 …)** | **NO-GO** until written BD-UL-02 |
| **Count registration-only directs for Level Income unlock** | **NO-GO** (BD-UL-01 / BD-UL-03) |
| **Count Tour Only toward Level Income unlock / ROI** | **NO-GO** |

Existing members and purchases are evaluated from current DB records — no re-registration or repurchase required.

---

## 1. Root cause (evidence)

### 1.1 Level unlock

Live accrual and dashboards use **legacy** unlock until a published version has `posting_authorized = true`:

| Qualifying directs (unique) | Legacy unlocked levels |
|-----------------------------|------------------------|
| 0 | **0** |
| 1 | 4 |
| 2 | 8 |
| 3 | 12 |
| 4 | 16 |
| ≥5 | 20 |

Client-preferred bands (active + 0 directs → 1–4, …, 4+ → 1–20) live only in draft **`proposed_activation_unlock_v1`** and are **not** posting-authorized.

**Qualifying direct** = sponsored member with ≥1 **approved income-eligible** purchase (`tour_roi` / `premium_tour_roi` / `roi`, or income-config code + `level_commission_eligible`).

**Does not unlock under live rules:**

- Registration / “direct joining” alone  
- Tour Only (`package_type = tour`)  
- Pending / rejected purchases  
- Multiple packages by the same person (still **one** qualifying direct)

Local diagnostic (`deltasupport:diagnose-level-unlock-business`) on this MySQL confirmed: activated member with 0 qualifying directs → **0 unlocked** with explicit reason.

### 1.2 Tour Only missing from Total Business

`TeamBusinessCalculationService::purchaseSums()` previously reused **income** filters (`ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES` + `level_commission_eligible`). Tour Only failed both, so approved Tour Only USDT `price_usd` snapshots were excluded from First Level / Total Team Business.

**Rank “Full Team Business”** still uses legacy `investments` via `RankService` (separate metric — see BD-BV-01).

---

## 2. Impact comparison (policy — do not auto-authorize)

| Qualification basis | Unlock effect | Risk |
|---------------------|---------------|------|
| Registered directs | Unlock without purchase | **High** |
| Unique income-eligible approved purchases (**current live**) | Matches commission semantics | Low if unchanged |
| Any approved purchase including Tour Only | Tour Only unlocks levels | **High** |
| Purchase count (not unique members) | Inflates unlock | **High** |

---

## 3. Fixes implemented locally

### 3.1 Tour Only → Business Volume (safe)

- **`PackageBusinessVolumeEligibility`** — BV types include Tour + ROI types; approved USDT snapshots; excludes pending/rejected/VERIFY-1/local-test.  
- **`TeamBusinessCalculationService`** uses BV eligibility only (rewards + dashboard + admin directory + catalog report).  
- **Does not** change `qualifyingPurchaseConstraint()` → no ROI / level commission / unlock from Tour Only alone.

### 3.2 Level unlock clarity (no silent policy change)

- `forMember()` / `summariesForMemberIds()` expose registered vs qualifying directs, legacy unlock, proposed-band preview, `rule_mode`, applicable note.  
- Member eligibility UI shows applicable rule vs preview.  
- Dashboard BV copy clarifies Tour Only inclusion without implying Level Income unlock.  
- Unlock bands remain Admin-configurable in Commission Settings (not hard-coded in multiple views).

### 3.3 Diagnostics

```bash
php artisan deltasupport:diagnose-level-unlock-business
php artisan deltasupport:diagnose-level-unlock-business --member=STXXXX --json
```

Read-only. No wallet/commission/purchase writes. No cached BV table exists — totals are computed live from `package_purchases`; no reconciliation job required for BV.

---

## 4. Changed file inventory

| File | Change |
|------|--------|
| `app/Services/PackageBusinessVolumeEligibility.php` | **New** BV eligibility |
| `app/Services/Rewards/TeamBusinessCalculationService.php` | Use BV eligibility |
| `app/Services/ReferralLevelEligibility.php` | Richer summaries; safer `levelsForCount` |
| `app/Http/Controllers/Member/WalletController.php` | Pass rule mode / dual unlock |
| `app/Http/Controllers/Admin/LevelIncomeRuleController.php` | Ladder activation context |
| `app/Http/Controllers/Admin/OperationsController.php` | Ladder + BV type labels |
| `resources/views/member/finance/eligibility.blade.php` | Applicable rule messaging |
| `resources/views/member/dashboard.blade.php` | BV copy (Tour Only clarity) |
| `app/Console/Commands/DiagnoseLevelUnlockAndBusinessVolume.php` | **New** diagnostic |
| `tests/Feature/MemberBusinessSummaryTest.php` | Tour Only BV expectations |
| `tests/Feature/LevelUnlockAndBusinessVolumeFixTest.php` | **New** regression suite |
| `tests/Feature/LevelIncomeRuleAdministrationTest.php` | Activation-aware assert |
| `docs/theme-investigation/level-unlock-and-total-business-fix-report.md` | This report |

**Migrations:** None for BV/display. Level Income rule tables already present from prior release.

---

## 5. Financial safety

| Concern | Status |
|---------|--------|
| ROI from Tour Only | Still blocked |
| Level commission from Tour Only | Still blocked |
| Associate commission | Unchanged / posting disabled |
| Wallet / historical backfill | Not touched |
| Payment controls / emergency stop / scheduler | Unchanged |
| Proposed unlock bands live | **Not** authorized |
| Purchase snapshots / catalog price | BV uses stored `price_usd` only |

---

## 6. Before / after examples

**Business volume (root: Tour Only 200 + ROI tree 25):**

| Metric | Before | After |
|--------|--------|-------|
| Total Team Business | 25.0000 | **225.0000** |

**Unlock (activated, 1 registered direct with Tour Only only):**

| Metric | Value (unchanged policy) |
|--------|--------------------------|
| Registered directs | 1 |
| Qualifying directs | 0 |
| Unlocked (legacy live) | **0** |
| Proposed preview (display) | 4 |

---

## 7. Tests & build

```
Tests:    686 passed (6811 assertions)
Vite production build: SUCCESS
```

Prior baseline after applicability work: 676. This change adds `LevelUnlockAndBusinessVolumeFixTest` (+9) and related asserts.

---

## 8. Production release preparation

### Read-only production diagnostic (before/after deploy)

```bash
cd /home/wwwzeont/deltasupport.online
php artisan deltasupport:diagnose-level-unlock-business --json | tee /tmp/unlock-bv-before.json
# after deploy:
php artisan deltasupport:diagnose-level-unlock-business --json | tee /tmp/unlock-bv-after.json
```

Compare Tour Only purchase count / amount and sample activated-with-zero-unlock rows.

### Safe deploy sequence

1. Backup DB + current `public/build`.  
2. Deploy this release tag/commit.  
3. `composer install --no-dev --optimize-autoloader`  
4. **No new migrate required** for this BV/display fix (confirm `migrate:status`).  
5. `npm ci && npm run build` (or ship CI `public/build`).  
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`  
7. Smoke: member dashboard Total Team Business for a Tour Only purchaser; eligibility page rule note; Commission Settings still unauthorized.  
8. Confirm env: payment-controls / associate / tour income posting remain false.  
9. **Do not** authorize Level Income posting in this window.

### Rollback

```bash
git checkout <previous_release_tag>
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

No schema rollback. Revert restores Tour Only BV exclusion and prior unlock display helpers. Does not reverse any wallet rows (none created by this change).

### Git (local — commit only when requested)

```bash
cd C:\xampp\htdocs\delta_support
git status
# commit when explicitly authorized
```

---

## 9. Remaining business decisions

1. **BD-UL-01** — Keep unique income-qualifying directs? (Recommended: yes)  
2. **BD-UL-02** — Authorize proposed activation unlock for posting? Effective date + applicability?  
3. **BD-UL-03** — Registration-based unlock? (Recommended: no)  
4. **BD-BV-01** — Move Rank Full Team Business from `investments` to package BV?  
5. **BD-RW-01** — Confirm reward campaign thresholds after Tour Only BV inclusion  

---

## Explicit non-actions

- No production deploy from this workspace  
- No financial authorization / live posting  
- No historical reward issuance  
- No member/purchase/financial record delete or recreate  
