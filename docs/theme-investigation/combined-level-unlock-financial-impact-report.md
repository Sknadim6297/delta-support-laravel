# Combined Level Unlock — Option B Financial Impact Audit Report

**Project:** Delta Support  
**Date:** 2026-11-26  
**Scope:** Read-only financial impact preview — live legacy Level Income vs proposed Combined Level Unlock (Option B / `existing_prospective` simulation).  
**Constraints:** Local only. No deploy, publish, authorize, financial posting, scheduler changes, wallet/ledger writes, historical backfill, or Associate Commission activation.

---

## Verdict

| Item | Status |
|------|--------|
| Read-only liability projection | **Implemented** |
| Uses actual proposed draft (`proposed_activation_unlock_v1`) | **Yes** |
| Supports `existing_prospective` | **Yes** (simulated; does not authorize) |
| Member / purchase / level diffs | **Yes** |
| Grandfathering + activation + payment controls + package terms | **Respected** |
| Tour Only BV separated from commission generation | **Yes** |
| ROI path unchanged | **Verified via live ROI plan check** |
| Associate Commission | **Remains off** |
| Automated no-write tests | **3 passed** |
| Full suite | **744 passed (7173 assertions)** |
| Vite build | **Successful** |
| Production authorize / posting | **Not performed** |

Production unlock diagnostics already showed: 82 members; 54 gain; 17 activated-with-zero-live; 28 unchanged; 0 protected reductions; Tour Only BV 200 USDT / 4 purchases counted once; Nov 23–25 financial audits clean. This work adds the **USDT commission liability** layer for Option B.

---

## 1. What was delivered

### Service

`App\Services\LevelIncomeLiabilityProjection`

- Loads the real candidate draft (default `proposed_activation_unlock_v1`), including combined joining/purchase settings, bands, rates, grandfather flags.
- For a specified future business date, scans all approved source purchases.
- For each commission-eligible purchase inside waiting-period + duration window:
  - Builds **legacy** upline commission lines (income-eligible unique directs + legacy ladder + legacy rates).
  - Builds **proposed** upline commission lines under simulated Option B applicability (`existing_prospective` by default), using candidate qualification metric, bands, rates, personal activation, and grandfathering (`protected = max(proposed, min(snapshot_legacy, current_legacy))`).
- Aggregates:
  - Current (legacy) daily commission
  - Proposed daily commission
  - Additional daily liability
  - Remaining projected liability under stated assumptions
  - Member-wise, purchase-wise, level-wise differences
- Reuses unlock analyzer for level-gain summary and Tour Only BV audit.
- Calls live `PackageIncomeAccrual::previewReal()` only as an **ROI unchanged check** (ROI is not re-planned under candidate rules).

### Artisan command

```bash
php artisan deltasupport:preview-level-income-liability --date=YYYY-MM-DD --json
```

Options:

| Option | Default | Meaning |
|--------|---------|---------|
| `--date=` | required | Business date to project |
| `--rule-version=` | `proposed_activation_unlock_v1` | Candidate version code |
| `--applicability=` | `existing_prospective` | Simulated applicability |
| `--effective-from=` | candidate / date | Simulated effective date |
| `--detail-limit=` | `100` | Max detail rows |
| `--json` | off | Machine-readable report |

`--version` is **not** used (conflicts with Symfony’s global `--version`).

---

## 2. Explicit assumptions (stated in report output)

1. Read-only; `mutates=false`.  
2. Candidate is treated **as if authorized** only inside the simulation for the chosen applicability/effective date — live DB authorization is not changed.  
3. No historical backfill: prior business dates are not recalculated.  
4. Unlock metric and payment controls are frozen as of the projection business date.  
5. Remaining projected additional liability ≈  
   `Σ (additional_daily_per_purchase × remaining_commission_days_for_that_purchase)`.  
6. Tour Only unlock qualification (if enabled on draft) does **not** create commission generation for Tour Only packages.  
7. Package terms (pool, wait, duration), payment stops, personal activation, and admin-root exclusion remain authoritative.  
8. Associate Commission posting must remain off.  
9. ROI planning path is unchanged; only Level Income unlock/rates differ in the proposed branch.

---

## 3. Production command and expected output

### Exact production command (read-only)

```bash
php artisan deltasupport:preview-level-income-liability \
  --date=2026-11-27 \
  --rule-version=proposed_activation_unlock_v1 \
  --applicability=existing_prospective \
  --effective-from=2026-11-15 \
  --json
```

Human-readable (same args without `--json`):

```bash
php artisan deltasupport:preview-level-income-liability \
  --date=2026-11-27 \
  --applicability=existing_prospective \
  --effective-from=2026-11-15
```

Choose `--date` as the first future business date under consideration and `--effective-from` as the BD-approved Option B effective date. Do **not** authorize posting from this command.

### Expected output shape (JSON highlights)

```json
{
  "read_only": true,
  "mutates": false,
  "option": "B",
  "candidate": {
    "version_code": "proposed_activation_unlock_v1",
    "posting_authorized": false,
    "direct_qualification_basis": "combined_joining_and_purchase",
    "applicability_simulated": "existing_prospective"
  },
  "totals": {
    "legacy_commission_daily": "...",
    "proposed_commission_daily": "...",
    "additional_daily_liability": "...",
    "remaining_projected_additional_liability": "...",
    "roi_daily_unchanged_check_total": "...",
    "associate_commission_posting_enabled": false
  },
  "unlock_summary": {
    "analyzed": 82,
    "gain": 54,
    "loss": 0,
    "unchanged": 28,
    "activated_with_zero_live_unlock": 17,
    "entitlement_reductions": 0
  },
  "tour_only_business_volume": {
    "purchase_count": 4,
    "total_usdt": "200.0000",
    "counted_once": true
  },
  "member_differences": [ "... non-zero additional daily rows ..." ],
  "purchase_differences": [ "... remaining days + legacy/proposed/additional ..." ],
  "level_differences": [ "... levels 1–20 ..." ],
  "safety": {
    "wallet_writes": 0,
    "accrual_writes": 0,
    "authorization_changed": false,
    "associate_engine_activated": false,
    "historical_backfill": false
  }
}
```

Unlock summary counts above mirror the confirmed production diagnostic profile; **USDT totals are environment-specific** and must be taken from the command run against production/replica data. Local suites validate mechanics and zero-write safety, not production dollar figures.

Companion unlock diagnostic (levels / BV only):

```bash
php artisan deltasupport:diagnose-level-unlock-business --all --json
```

---

## 4. Changed files

| File | Role |
|------|------|
| `app/Services/LevelIncomeLiabilityProjection.php` | Read-only legacy vs proposed commission liability engine |
| `app/Console/Commands/PreviewLevelIncomeLiability.php` | Artisan CLI |
| `tests/Feature/LevelIncomeLiabilityProjectionTest.php` | No-write + existing_prospective + CLI tests |
| `docs/theme-investigation/combined-level-unlock-financial-impact-report.md` | This report |

No migrations. No authorization changes. No scheduler / Associate / ROI engine edits.

---

## 5. Safety verification

| Control | Result |
|---------|--------|
| Wallet / ledger writes | None (DB write listener in tests = 0) |
| `package_roi_accruals` / `package_level_accruals` | Unchanged |
| Purchase snapshots / prices | Unchanged |
| `posting_authorized` on candidate | Remains false in tests |
| Associate Commission | `posting_enabled` false; engine not activated |
| Historical backfill | Explicitly false |
| Tour Only commission generation | Still requires level-eligible source packages |
| Nov 23–25 production audit findings | Out of scope to re-run here; prior clean audits stand |

---

## 6. Test results

### Focused

```
Tests\Feature\LevelIncomeLiabilityProjectionTest
 ✓ projection is read only and performs no financial writes
 ✓ existing prospective increases liability when unlock expands
 ✓ artisan command is read only json
Tests: 3 passed
```

### Full suite

```
Tests:    744 passed (7173 assertions)
```

Logs: `storage/logs/liability-preview-test-run.txt`, `storage/logs/liability-preview-junit.xml`

### Vite

```
vite build — success (~10.1s)
```

---

## 7. Remaining business decisions (still unauthorized)

1. Confirm Option B formula weights/policy already staged on the draft.  
2. Confirm `--effective-from` business date for `existing_prospective`.  
3. Review projected **additional daily** and **remaining additional** liability from the production command run.  
4. Confirm grandfathering capture is current before any authorize.  
5. Keep Associate Commission off.  
6. Only after written BD approval: publish → capture baseline if needed → authorize (separate confirms).  
7. Do not backfill Nov history; prospective dates only.

---

## 8. Deployment / production use (do not execute posting here)

1. Deploy code containing the projection service + command.  
2. On production or a read replica with production data: run the exact command in §3.  
3. Archive JSON output with BD review package.  
4. Do **not** publish/authorize/execute `financial:daily` as part of this audit.  
5. Keep `DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING=false`.

**Implemented and verified locally only.**
