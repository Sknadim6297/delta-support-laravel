# Panel V2 — Final Report (T4)

Local verification only. Not deployed.

**Checkpoint before legacy removal:** git tag `t4-pre-legacy-removal` (`2c26b76`).

## Executive summary

Panel V2 is the sole Admin/Member shell. Lux runtime switching, Lux Vite entries, and Lux shell Blade/CSS/JS sources were removed after route migration and visual verification. Public website fingerprints and Rewards popup behavior are unchanged. Financial logic, MoneyDisplay, auth, and database were not modified in T4.

## Final frontend architecture

```
Admin/Member layouts (V2 only)
  └─ @vite panel.css + panel-v2.css + panel.js + panel-v2.js
  └─ optional @vite features/rewards.css (rewards pages / dashboard popup)

panel.css (barrel)
  ├─ tokens / member-module
  ├─ panel/tokens, layout, components/*, responsive
  ├─ features/dashboard, packages
  └─ panel/content-overrides

panel-v2.css (barrel)
  └─ panel-v2/tokens, shell, navigation, grids, components, responsive, pilot

Public (untouched entries)
  └─ site.css, auth-portal.css, register.css, site.js
```

Shell DOM: `.pv-app` → sidebar (`layouts/panel/sidebar-*`) → header/footer → `.ds-content`.

## Migrated routes

See `docs/theme-investigation/panel-v2-t3-layout-checklist.md` for the T3 route table. T4 confirmation:

| Panel | Areas | Status |
| --- | --- | --- |
| Admin | Dashboard, Members, Packages, Payments, Invoices, ROI/Level, Wallets, Activation, Investments, Transfers, Withdrawals, Rewards, Reports, Support, Settings | V2 shell + shared grids/components |
| Member | Dashboard, Account/PIN, Network/Referral/Tree/Rank, Commerce, Wallets/Transfers, Withdrawals, Rewards+popup, Support, Reports, Investments | V2 shell + shared grids/components |

Empty salary report stubs remain empty-state pages (no invented data).

## Legacy files removed

| Path | Reason |
| --- | --- |
| `resources/css/lux-shell.css` | Lux shell only |
| `resources/css/lux-pages.css` | Lux page chrome only |
| `resources/js/lux-shell.js` | Lux sidebar/header JS only |
| `resources/views/partials/lux-bar.blade.php` | Lux header |
| `resources/views/admin/partials/sidebar.blade.php` | Lux admin nav |
| `resources/views/member/partials/sidebar.blade.php` | Lux member nav |
| `resources/views/components/navigation/lux-group.blade.php` | Lux nav |
| `resources/views/components/navigation/lux-link.blade.php` | Lux nav |
| Vite entries for lux-shell / lux-pages / lux-shell.js | Unused after cutover |

Built Lux assets are no longer emitted by `npm run build`.

## Remaining dependencies (intentional)

| Item | Why kept |
| --- | --- |
| `resources/css/panel.css` + `panel/**` | Shared forms, tables, tree canvas, finance/report styles used by V2 |
| `:is(.wrapper, .pv-app)` bridges in panel CSS | Harmless dual selectors; `.wrapper` no longer rendered |
| `resources/js/panel.js` | Copy/share, password toggles, genealogy helpers, status chips |
| `resources/css/features/rewards.css` | Approved rewards UI + popup |
| `App\Support\PanelTheme` | Always returns `v2` (compat helper; no runtime Lux toggle) |
| `panel/components/modals.css` tree styles | Genealogy canvas (not Lux-shell) |

No `ds-shell` selectors remain in source.

## Bundle size comparison (production build)

| Asset | After T4 | Notes |
| --- | --- | --- |
| `panel-v2-DL4ij-jF.css` | 27.35 kB (5.23 kB gzip) | Shell/grids |
| `panel-YtZbyNEO.css` | 83.80 kB (13.37 kB gzip) | Shared panel |
| `panel-CC8RmISc.js` | 10.76 kB (3.43 kB gzip) | Shared panel JS |
| `panel-v2-C3L6-056.js` | 1.88 kB (0.67 kB gzip) | Shell JS |
| `rewards-Cy5MydHO.css` | 6.53 kB | **Unchanged** |
| `site-DGtptVhd.css` | 62.89 kB | **Unchanged** |
| `auth-portal-qJ3FFg57.css` | 46.11 kB | **Unchanged** |
| `register-BBxcR53B.css` | 6.63 kB | **Unchanged** |
| lux-shell / lux-pages CSS+JS | **removed from build** | Were ~32 kB CSS + ~1.6 kB JS |

Manifest no longer lists lux entries.

## Test results

| Suite | Result |
| --- | --- |
| Full PHPUnit | **648 passed** (6588 assertions) — `storage/logs/t4-full-test.txt` |
| `PanelThemeTest` | 2 passed — theme always v2 |
| `RewardCampaignPopupMediaTest` | **8 passed** (97 assertions) — popup/media preserved |

Do not weaken tests to pass.

## Responsive verification (CDP)

| Page | Width | KPI cols | Equal height | Overflow | Lux CSS |
| --- | --- | --- | --- | --- | --- |
| Admin Members | 1440 | 4 | yes | no | false |
| Admin Dashboard | 1440 | multi KPI | — | — | false |
| Member Wallets | 1440 | 4 | yes (T3) | no | false |
| Member Wallets | 768 | 2 | yes | no | false |
| Member Wallets | 390 | 2 | — | no | false |
| Member Wallets | ~852 laptop | 2 | yes | no | false |

## Screenshots

`docs/theme-investigation/screenshots/t4/`:

- `t4-admin-dashboard-1440.png`
- `t4-member-wallets-768.png`
- `t4-member-wallets-390.png`
- Prior T3 references copied for before/after context (`t3-admin-members-final-1440.png`, `t3-member-wallets-1440.png`)

Earlier baselines: `screenshots/approval/before-admin-dashboard-lux.png`, T3 folder.

## Public website verification

| Check | Result |
| --- | --- |
| `site-DGtptVhd.css` fingerprint | Unchanged |
| `auth-portal-qJ3FFg57.css` | Unchanged |
| `register-BBxcR53B.css` | Unchanged |
| Panel layouts import public CSS | No |

## Local development workflow

```bash
npm install
npm run dev          # Vite HMR; Apache serves public/
php artisan serve    # optional; XAMPP already hosts /delta_support/public
php artisan test
npm run build
```

Panel pages always load V2. `PANEL_THEME` in `.env` is ignored at runtime (documented in `.env.example`).

## Production deployment instructions

1. Ensure code includes T4 commit (Lux sources removed, V2 layouts only).
2. `npm ci && npm run build`
3. Deploy `public/build` + application code together.
4. Clear config/view caches: `php artisan optimize:clear` (or `config:cache` / `view:cache` as used in prod).
5. Confirm panel HTML has `data-panel-theme="v2"` and no `lux-*.css` in network waterfall.
6. Smoke: admin dashboard, member wallets, rewards popup after login, public home/login/register.

**Do not deploy from this local session without an explicit production release process.**

## Rollback instructions

```bash
git fetch --tags
git checkout t4-pre-legacy-removal
npm ci && npm run build
php artisan optimize:clear
```

That tag restores Lux dual-path layouts, Lux Vite entries, and `PANEL_THEME` lux/v2 switching.

## Protected areas (unchanged in T4)

- Public website / login / registration design and CSS/JS
- Financial calculations, ROI/Level Income, wallets/ledger, purchases, reward eligibility
- Scheduled jobs, authz, migrations/DB records, MoneyDisplay behavior
- Approved Rewards popup implementation

## Deployment readiness

**Ready for deployment** after ops review of this report and a staged environment smoke test. Local T4 verification complete: V2-only shell, Lux removed, public fingerprints stable, rewards popup tests green, responsive KPI grids verified.
