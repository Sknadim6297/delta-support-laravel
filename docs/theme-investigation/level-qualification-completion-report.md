# Dynamic Level Qualification Completion Report

**Project:** Delta Support (`C:\xampp\htdocs\delta_support`)  
**Production path (ops):** `/home/wwwzeont/deltasupport.online`  
**Date:** 2026-11-24  
**Scope:** Configurable Level Unlock qualification (versioned, draft-safe). **Local only — no deploy, no authorize, no historical backfill, no Associate Commission posting.**

---

## Verdict

| Item | Status |
|------|--------|
| Versioned qualification basis + Tour Only unlock flag | **Implemented (draft/versioned)** |
| Live posting behavior change | **None** — still legacy until `posting_authorized` |
| Tour Only → Total Business (prior fix) | **Preserved** |
| Financial history / wallets / snapshots | **Untouched** |
| Full test suite | **694 passed** |
| Vite build | **Successful** |
| Authorize `proposed_activation_unlock_v1` | **NO-GO** — needs business approval |

---

## 1. What was implemented

### 1.1 Schema (migration `2026_11_24_170000_add_level_income_qualification_settings`)

On `level_income_rule_versions`:

| Column | Purpose | Seed |
|--------|---------|------|
| `direct_qualification_basis` | How directs/purchases are counted for unlock | `legacy_live_v1` → `income_eligible_directs`; `proposed_activation_unlock_v1` → `approved_package_directs` |
| `tour_only_qualifies_for_unlock` | Tour Only may count for unlock (not ROI/commission generation) | legacy `false`; proposed draft `true` |

### 1.2 Qualification bases (Admin-configurable per version)

| Basis | Counting rule |
|-------|---------------|
| `registered_directs` | Unique sponsored members (registration only) |
| `approved_package_directs` | Unique directs with ≥1 approved package (Tour filter optional) |
| `income_eligible_directs` | Unique directs with ≥1 income-eligible approved package (live default) |
| `approved_package_purchase_count` | Count of approved direct purchases (**not** unique members) |

Unique-member modes and purchase-count mode remain explicitly separate.

### 1.3 Proposed unlock bands (unchanged draft)

Still in unauthorized draft `proposed_activation_unlock_v1`:

| Condition | Levels |
|-----------|--------|
| No qualifying personal approved package | 0 |
| Activated, 0 directs | 1–4 |
| Activated, 1 direct | 1–8 |
| Activated, 2 directs | 1–12 |
| Activated, 3 directs | 1–16 |
| Activated, 4+ directs | 1–20 |

### 1.4 Runtime wiring (unauthorized-safe)

- **Live / legacy:** income-eligible unique directs; Tour Only excluded from unlock.
- **Authorized version only:** uses that version’s `direct_qualification_basis` + `tour_only_qualifies_for_unlock` for count and personal activation.
- Accrual paths already go through `ReferralLevelEligibility::forMember(..., $purchase, $businessDate)` → purchase-scoped applicability preserved (`future_purchases_only` / `existing_prospective`).

### 1.5 Admin UI

Commission Settings → Level Unlock Rules:

- Select qualification basis  
- Checkbox: Tour Only qualifies for unlock (independent of ROI/commission generation)  
- Cloned into drafts; audited via existing snapshot/audit trail  

### 1.6 Member preview

Eligibility page shows read-only **Qualification preview**:

- Registered / approved purchasing / purchase count / Tour Only purchasing directs  
- Currently unlocked vs expected under each mode  
- Candidate version code + locked reason  

Existing members are evaluated from DB referral + purchase records — **no re-registration or repurchase**.

### 1.7 Diagnostics

```bash
php artisan deltasupport:diagnose-level-unlock-business
php artisan deltasupport:diagnose-level-unlock-business --member=MEMBERID --json
```

Read-only: current vs proposed unlock sample, Tour Only BV totals, per-member qualification preview. Does **not** authorize or post.

---

## 2. Financial safety (confirmed)

| Control | Status |
|---------|--------|
| Purchase snapshots / commission pools / ROI durations | Unchanged |
| Wallet balances / ledger history | Unchanged |
| Individual payment controls / emergency stop | Unchanged |
| `future_purchases_only` / `existing_prospective` | Enforced |
| Auto-authorize / historical backfill | **Not performed** |
| Associate Commission posting | **Remains disabled** |
| Tour Only ROI / Level Income generation | **Still excluded** (BV only) |

---

## 3. Settings requiring business approval before activation

Do **not** publish+authorize until written approval of each item:

| ID | Setting | Proposed draft default | Risk if activated without review |
|----|---------|------------------------|----------------------------------|
| **BD-Q-01** | `direct_qualification_basis` | `approved_package_directs` | Broadens unlock vs live income-eligible unique directs |
| **BD-Q-02** | `tour_only_qualifies_for_unlock` | `true` | Tour Only would unlock levels (still no ROI/commission from Tour Only) |
| **BD-Q-03** | Unlock bands (0 directs → 1–4 … 4+ → 1–20) | Already in proposed draft | Changes ladder vs legacy (0 directs → 0) |
| **BD-Q-04** | Applicability | Prefer `future_purchases_only` | `existing_prospective` applies new rules to older purchases from effective date forward (no backfill, but prospective accrual changes) |
| **BD-Q-05** | `effective_from` | Must be set before authorize | Defines purchase/business-date boundary |
| **BD-Q-06** | `posting_authorized` | Must stay `false` until BD-Q-01…05 signed | Flips live accrual off hard-coded legacy |

**Explicitly not approved by this work:** registration-only unlock, purchase-count inflation, Associate Commission enablement, historical recalculation.

---

## 4. Production readiness (deploy later — not executed)

### 4.1 Pre-deploy (local verified)

```bash
cd C:\xampp\htdocs\delta_support
php artisan migrate --force --no-interaction
php artisan test
npm run build
php artisan deltasupport:diagnose-level-unlock-business --limit=50
```

**Results (this session):** migration applied locally; **694 tests passed**; Vite build OK.

### 4.2 Exact production deployment commands (ops — do not run until GO)

```bash
cd /home/wwwzeont/deltasupport.online
php artisan down
git pull   # or rsync approved release artifact
composer install --no-dev --optimize-autoloader
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan deltasupport:diagnose-level-unlock-business --limit=100 --json > /tmp/level-unlock-diag.json
php artisan up
```

**Cache refresh after config/UI deploy:**

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.3 Verification (read-only)

```bash
php artisan deltasupport:diagnose-level-unlock-business --member=<ID>
# Confirm posting_authorized=false on proposed_activation_unlock_v1
# Confirm live unlock still matches legacy for sample members
# Spot-check Total Business still includes approved Tour Only BV
```

### 4.4 Activation sequence (only after BD-Q-01…06)

1. Edit draft qualification + bands in Commission Settings  
2. Publish with applicability + `effective_from`  
3. Review impact preview + diagnostic current vs proposed  
4. Explicit **Authorize posting** (separate confirm; `existing_prospective` needs second confirm)  
5. Monitor `financial:daily` dry/controlled run under payment controls  

**Revoke path:** Commission Settings → Revoke posting authorization → live returns to hard-coded legacy.

---

## 5. Files touched

| Area | Path |
|------|------|
| Migration | `database/migrations/2026_11_24_170000_add_level_income_qualification_settings.php` |
| Service | `app/Services/LevelIncomeDirectQualification.php` |
| Model | `app/Models/LevelIncomeRuleVersion.php` |
| Admin | `app/Services/LevelIncomeRuleAdministration.php`, `app/Http/Controllers/Admin/LevelIncomeRuleController.php` |
| Eligibility | `app/Services/ReferralLevelEligibility.php` |
| UI | `resources/views/admin/finance/commission-settings.blade.php`, `resources/views/member/finance/eligibility.blade.php` |
| Member controller | `app/Http/Controllers/Member/WalletController.php` |
| Diagnostic | `app/Console/Commands/DiagnoseLevelUnlockAndBusinessVolume.php` |
| Tests | `tests/Feature/LevelIncomeQualificationSettingsTest.php` (+ updates to admin / unlock BV tests) |

---

## 6. Test / build summary

| Check | Result |
|-------|--------|
| `php artisan test` | **694 passed** (6858 assertions) |
| `npm run build` | **OK** |
| Live `posting_authorized` after this work | **Still false** for proposed draft |
| Tour Only BV regression suite | **Passed** |

---

## 7. Bottom line

Configurable Level Unlock qualification is complete **as versioned Admin configuration**. Live Level Unlock business behavior remains on the legacy ladder until business approves **BD-Q-01 through BD-Q-06** and an Admin explicitly authorizes posting. Tour Only continues to contribute to Total Business without generating ROI or Level Income.
