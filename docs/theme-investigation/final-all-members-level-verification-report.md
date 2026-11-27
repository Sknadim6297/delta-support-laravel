# Final All-Members Level Unlock Verification Report

**Audit type:** Independent Level Unlock verification (all registered members)  
**Code commit under review:** `808f68d4` (local HEAD `808f68d`)  
**Requested production rule:** `draft_from_proposed_activation_unlock_v1_054322`  
**Requested effective business date:** `2026-11-28` Asia/Kolkata  
**Generated:** 2026-11-27 (local read-only run)  
**Constraints:** No deploy, no authorize/revoke, no income generation, no production data/cache changes, no financial setting changes.

---

## 1. Scope and data caveat (critical)

| Item | Result |
|------|--------|
| Requested production version in local DB | **NOT FOUND** |
| Local DB | `delta_support_server` |
| Registered members verified | **82 / 82** |
| Local authorized Option B rule used | `proposed_activation_unlock_v1` |

`php artisan deltasupport:verify-all-members-level-unlock --rule-version=draft_from_proposed_activation_unlock_v1_054322 --business-date=2026-11-28` fails with **Rule version not found**.

Local authorized configuration (verified instead):

| Field | Value |
|-------|-------|
| version_code | `proposed_activation_unlock_v1` |
| status | published |
| posting_authorized | **yes** |
| applicability | existing_prospective |
| effective_from | **2026-11-27** |
| basis | combined_joining_and_purchase |
| purchase scope | personal_approved_purchases |
| repeat mode | each_approved_purchase |
| joining + purchase | enabled |
| double credit | allow_sum (personal scope → sum) |
| tour_only_qualifies_for_unlock | yes |
| grandfather | yes (82 snapshots) |

**This report does not claim production `draft_from_…_054322` was verified.** Re-run the production-safe command below against production (read replica / ops host) with that version code.

---

## 2. Independent calculation method

Expected values were computed **only from raw SQL/DB counts** in `IndependentLevelUnlockAuditor` — **not** from `LevelIncomeRuleResolver` / `LevelIncomeDirectQualification` for the expected side.

| Input | Independent source |
|-------|-------------------|
| Joining | `COUNT(members WHERE sponsor_id = member AND member_id <> self)` |
| Approved personal purchases | `package_purchases` status=`approved`, `reviewed_at IS NOT NULL`, package_type in BV set (includes Tour when unlock flag on); **each row counted** |
| Pending / rejected | Counted separately; **excluded** from approved metric |
| Activated | `approved_personal_purchases > 0` |
| Expected (proposed) | `activated ? min(20, 4 × (joining + approved_purchases)) : 0` |
| Band cross-check | `level_income_unlock_bands` for the version (must equal formula) |
| Legacy level | Hard-coded ladder on **income-eligible direct count** (Tour Only does **not** count as income-eligible) |
| Snapshot | `level_income_unlock_grandfather_snapshots.legacy_unlocked_levels` |
| Protected | `max(proposed, min(snapshot, current_legacy))` when snapshot exists; else proposed |
| Runtime / dashboard | `ReferralLevelEligibility::forMember()` (member + admin display path) — comparison only |

Level Income **eligibility** (own income-eligible purchase or income-eligible directs) is reported separately from unlock count. Tour Only unlock does **not** enable ROI/commission generation.

---

## 3. Verification summary (local DB)

Business date audited: **2026-11-28** Asia/Kolkata (on/after local effective 2026-11-27).

| Metric | Value |
|--------|-------|
| Members verified | **82** |
| PASS | **82** |
| FAIL | **0** |
| Exact mismatches | **None** |
| Band formula cross-check | All match |
| Dashboard = runtime | All match |

Also re-run at **2026-11-26** (before local effective): display protected levels still **82/82 PASS** (authorized display path). Accrual purchase-scoped mode for sample member **ST100000**: before → `legacy` unlock 4; on/after → `authorized` unlock 8 (no historical backfill of past accruals).

---

## 4. Boundary cases observed (local)

| Case | Members / evidence | Result |
|------|-------------------|--------|
| Grandfather floor > proposed | ST100026, ST100034, ST100038 — expected 0 (not activated), snapshot/legacy 4 → protected **4** | PASS |
| Repeat approved purchases | ST100008 (4), ST100009 (3), ST100015 (3), ST100016 (2), ST100073 (2) | PASS (each row counted) |
| Tour Only unlock without income types | ST100043, ST100057, ST100059, ST100060 (tour-only own purchase; unlock granted; not ROI income types) | PASS |
| Inactive / zero metric | 20 members with joining=0 and purchases=0 → expected/runtime 0 | PASS |
| Cap at 20 | ST100004 (5+1), ST100008 (1+4), ST100020 (6+1) → 20 | PASS |
| Pending exclusion | Covered by automated test (pending purchase not in approved count) | PASS |
| Effective-date accrual | ST100000 accrual mode flips legacy→authorized at effective boundary | Observed |

---

## 5. Per-member comparison (complete; not truncated)

| Member ID | Joining | Approved Purchases | Activated | Legacy Level | Expected Level | Protected Level | Runtime Level | Dashboard Level | PASS/FAIL |
|-----------|---------|--------------------|-----------|--------------|----------------|-----------------|---------------|-----------------|-----------|
| ST100000 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100001 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100002 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100003 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100004 | 5 | 1 | Y | 12 | 20 | 20 | 20 | 20 | PASS |
| ST100005 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100006 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100007 | 2 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100008 | 1 | 4 | Y | 4 | 20 | 20 | 20 | 20 | PASS |
| ST100009 | 1 | 3 | Y | 4 | 16 | 16 | 16 | 16 | PASS |
| ST100010 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100011 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100012 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100013 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100014 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100015 | 1 | 3 | Y | 4 | 16 | 16 | 16 | 16 | PASS |
| ST100016 | 1 | 2 | Y | 4 | 12 | 12 | 12 | 12 | PASS |
| ST100017 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100018 | 3 | 1 | Y | 12 | 16 | 16 | 16 | 16 | PASS |
| ST100019 | 2 | 1 | Y | 4 | 12 | 12 | 12 | 12 | PASS |
| ST100020 | 6 | 1 | Y | 8 | 20 | 20 | 20 | 20 | PASS |
| ST100021 | 2 | 1 | Y | 8 | 12 | 12 | 12 | 12 | PASS |
| ST100022 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100023 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100024 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100025 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100026 | 2 | 0 | N | 4 | 0 | 4 | 4 | 4 | PASS |
| ST100027 | 1 | 1 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100028 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100029 | 2 | 1 | Y | 8 | 12 | 12 | 12 | 12 | PASS |
| ST100030 | 3 | 1 | Y | 4 | 16 | 16 | 16 | 16 | PASS |
| ST100031 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100032 | 3 | 1 | Y | 12 | 16 | 16 | 16 | 16 | PASS |
| ST100033 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100034 | 2 | 0 | N | 4 | 0 | 4 | 4 | 4 | PASS |
| ST100035 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100036 | 2 | 1 | Y | 8 | 12 | 12 | 12 | 12 | PASS |
| ST100037 | 2 | 1 | Y | 8 | 12 | 12 | 12 | 12 | PASS |
| ST100038 | 2 | 0 | N | 4 | 0 | 4 | 4 | 4 | PASS |
| ST100039 | 3 | 1 | Y | 0 | 16 | 16 | 16 | 16 | PASS |
| ST100040 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100041 | 1 | 1 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100042 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100043 | 2 | 1 | Y | 4 | 12 | 12 | 12 | 12 | PASS |
| ST100044 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100045 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100046 | 1 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100047 | 1 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100048 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100049 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100050 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100051 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100052 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100053 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100054 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100055 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100056 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100057 | 1 | 1 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100058 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100059 | 1 | 1 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100060 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100061 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100062 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100063 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100064 | 1 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100065 | 1 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100066 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100067 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100068 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |
| ST100069 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100070 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100071 | 1 | 1 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100072 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100073 | 0 | 2 | Y | 0 | 8 | 8 | 8 | 8 | PASS |
| ST100074 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100075 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100076 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100077 | 0 | 0 | N | 0 | 0 | 0 | 0 | 0 | PASS |
| ST100078 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100079 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100080 | 1 | 1 | Y | 4 | 8 | 8 | 8 | 8 | PASS |
| ST100081 | 0 | 1 | Y | 0 | 4 | 4 | 4 | 4 | PASS |

## Exact mismatches

**None** on local DB for `proposed_activation_unlock_v1` as-of `2026-11-28`.

Production rule `draft_from_proposed_activation_unlock_v1_054322` was **not** present locally and was **not** verified here.

---

## 6. Historical / package terms

- No historical accrual backfill performed or required for this unlock display audit.  
- Accrual before effective date remains purchase-scoped **legacy** until business date ≥ effective_from.  
- Package terms, ROI rates, commission rates, wallets, and ledger rows were not modified.

---

## 7. Production-safe read-only command

```bash
php artisan deltasupport:verify-all-members-level-unlock \
  --rule-version=draft_from_proposed_activation_unlock_v1_054322 \
  --business-date=2026-11-28 \
  --output=docs/theme-investigation/final-all-members-level-verification-report-production.md \
  --fail-on-mismatch
```

Optional JSON:

```bash
php artisan deltasupport:verify-all-members-level-unlock \
  --rule-version=draft_from_proposed_activation_unlock_v1_054322 \
  --business-date=2026-11-28 \
  --json
```

Read-only: does not authorize/revoke, credit wallets, mutate purchases, clear caches, or change financial controls.

Implementation: `app/Services/IndependentLevelUnlockAuditor.php`, `app/Console/Commands/VerifyAllMembersLevelUnlock.php`.

---

## 8. Automated tests run locally

| Suite | Result |
|-------|--------|
| `IndependentLevelUnlockAuditorTest` | Passed (2) |
| CombinedLevelUnlock + Grandfathering + Applicability + Qualification | **55 passed** |

---

## 9. Verdict

| Claim | Status |
|-------|--------|
| Every **local** registered member independently verified vs Option B + grandfather | **YES — 82/82 PASS** |
| Production version `draft_from_proposed_activation_unlock_v1_054322` verified | **NO — version absent from local DB** |
| Deploy / authorize / income / data mutation | **Not performed** |

Re-run the command on production (or a read-only production replica) with the explicit `--rule-version` and `--business-date=2026-11-28` to complete production verification.
