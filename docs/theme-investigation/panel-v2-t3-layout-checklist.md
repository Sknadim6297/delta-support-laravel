# Panel V2 — T3 Layout Migration Checklist

Local-only. Public site / Rewards popup / finance / auth / DB unchanged. **T4 complete** — see `panel-v2-final-report.md`. Lux removed; rollback tag `t4-pre-legacy-removal`.

## Root cause (KPI cards stacking full-width)

| Cause | Fix |
| --- | --- |
| Lux grid selectors required `.wrapper` | Bridged to `:is(.wrapper, .pv-app)` |
| No V2 central grid/KPI system | `resources/css/panel-v2/grids.css` |
| Forms auto-fit stole `.ds-stat-grid` | Excluded grids + pin digits in `forms.css` |
| Finance forced 1-col under 720px | 2-col ≤720, 1-col ≤420 |
| Genealogy used Lux-only `lh-*` cards | Migrated to `x-ui.card` / `ds-filter-form` / `.pv-genealogy` |

## Central system

- Grids: `.pv-grid-2/3/4`, `.ds-stat-grid`, `.ds-mm-stats`, dash KPI grids
- Responsive: **4 → 2 (≤1100) → 2 (≤767/390) → 1 (≤360)**
- V2 assets only when `PANEL_THEME=v2` (no `lux-shell` / `lux-pages` in layout)
- Rank roadmap: `.pv-rank-roadmap` in `panel-v2/components.css`

---

## Admin routes

| Area | Route / view | Status | Visual QA |
| --- | --- | --- | --- |
| Dashboard | `admin/dashboard` | Done (pv-kpi-grid) | Screenshot `t3-admin-dashboard-1440.png` |
| All Members | `admin/members` | Done (`ds-mm-stats` 4-col@1440) | Pass — `t3-admin-members-final-1440.png`; lux:false |
| Active / Inactive | `admin/members/active`, `inactive` | Done | Shared mm stats |
| Register / Update | member registration / update | Done | Shared forms + mm stats |
| Referral Tree | `admin/genealogy/tree-view` | Done (shared workspace V2) | Pass (member tree verified) |
| Packages | index/show/create/edit/purchases | Done | Pass — `t3-admin-packages-1440.png` |
| Payments | pending/approved/rejected/settings | Done | Shared tables/filters |
| Invoices | index/show | Done | Shared |
| ROI / Level Income | finance/* | Done (`ds-stat-grid`) | Shared |
| Wallets | overview + show | Done (icons + 4-col) | Pass |
| Activation wallet | summary/credit/debit lists+forms | Done (`ds-reports`) | Migrated |
| Investments | active/closed/entry/withdrawal* | Done (`ds-reports`) | Migrated |
| Transfers | index/show | Done | Shared |
| Withdrawals | overview/index/settings/history | Done | Shared |
| Rewards | campaigns/eligible/reports/show | Done | **Popup preserved** |
| Reports | catalog, member, ROI, level, rank | Done | Shared |
| Salary report | `admin/report/salary` | Done (empty-state stub) | No data view |
| Support | tickets index/show | Done | Shared |
| Settings | general/financial/branding/security/profile | Done | Shared |

## Member routes

| Area | Route / view | Status | Visual QA |
| --- | --- | --- | --- |
| Dashboard | `member/dashboard` | Done | Screenshot prior |
| Profile / password / PIN | account pages | Done (PIN digit fix) | Pass |
| Referral link | `member/referral` | Done (`ds-stat-grid`) | Pass — `t3-member-referral-1440.png` |
| Register / Direct / Team | network pages | Done | Shared |
| Referral Tree | `member/genealogy/tree-view` | Done (no `lh-native`) | Pass — `t3-member-genealogy-1440.png` |
| Rank | `member/rank` | Done (KPI + roadmap) | Migrated |
| Packages / payments / invoices | commerce | Done | Shared |
| My Wallets / wallet show | earnings | Done (book KPIs restored) | Pass — `t3-member-wallets-1440.png`; 4×1@1440 |
| Eligibility / ROI / level history | finance | Done | Shared |
| Transfers | create/history | Done | Shared |
| Withdrawals | request/history | Done | Shared |
| Rewards | index/show + **popup** | Done / **preserved** | Approved popup unchanged |
| Support | tickets | Done | Shared |
| Reports | ROI / level / rank | Done (`ds-reports`) | Migrated |
| Salary report | `member/reports/salary` | Done (empty-state stub) | No data view |
| Investments | member invesment/* | Done (`ds-reports`) | Migrated |

---

## Browser QA (CDP)

| Width | Members KPIs | Wallets KPIs | Lux CSS loaded |
| --- | --- | --- | --- |
| 1440 | 4×1 | 4×1 | **false** |
| ≤1100 | 2×2 | 2×2 | false |
| 768 / 390 | 2×2 | 2×2 | false |
| 360 | 1×n | 1×n | false |

## Build / assets (latest)

| Bundle | Hash | Notes |
| --- | --- | --- |
| panel-v2 | `panel-v2-CtJlpKyM.css` | V2 only |
| panel | `panel-YtZbyNEO.css` | Shared panel CSS |
| rewards | `rewards-Cy5MydHO.css` | **Unchanged** |
| site / auth / register | `site-DGtptVhd` / `auth-portal-qJ3FFg57` / `register-BBxcR53B` | **Unchanged** |
| lux-* | present in build | Rollback only — **not loaded in V2** |

## Screenshots

Under `docs/theme-investigation/screenshots/t3/`:

- `t3-admin-members-final-1440.png` / `t3-admin-members-1440.png`
- `t3-admin-packages-1440.png`
- `t3-member-wallets-1440.png`
- `t3-member-referral-1440.png`
- `t3-member-genealogy-1440.png`
- Prior: `t3-admin-dashboard-1440.png`, `t3-member-dashboard-1440.png`, `members-390-after.png`

## Tests

- Settlement wallet overview asserts aligned with V2 `x-ui.stat` markup (label/value separate nodes).
- Full suite: see `storage/logs/t3-full-test.txt` after run.

## Preserved

- Rewards popup implementation
- Public website
- Financial logic / MoneyDisplay
- Authentication & permissions
- Database
- Lux theme files (rollback via `PANEL_THEME=lux`)
