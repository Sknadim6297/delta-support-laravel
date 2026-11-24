# Panel v2 Pilot Completion (T0–T2)

**Status:** Pilot ready for visual approval. Legacy Lux remains available via rollback. Not deployed. Lux files not deleted.

## Feature flag / rollback

| Mode | Env | Behavior |
| --- | --- | --- |
| New theme | `PANEL_THEME=v2` | `panel-v2` shell + assets; **no** lux-shell/pages/js |
| Legacy | `PANEL_THEME=lux` (default in `.env.example`) | Original Lux shell |

```powershell
# Enable v2 (local)
# PANEL_THEME=v2 in .env
php artisan config:clear

# Rollback
# PANEL_THEME=lux in .env
php artisan config:clear
```

Server-only. No public/user theme toggle.

## Architecture

- `App\Support\PanelTheme`
- `config/deltasupport.php` → `panel_theme`
- CSS: `resources/css/panel-v2.css` (+ tokens/shell/navigation/components/responsive/pilot)
- JS: `resources/js/panel-v2.js`
- Blade shell: `resources/views/layouts/panel/*`
- Nav: `x-navigation.panel-group` / `panel-link`
- Layouts branch in `admin/layouts/app` and `member/layouts/app`

## Pilot pages

1. Admin Dashboard — redesigned KPIs/cards/tables (`pv-*`)
2. Member Dashboard — v2 shell + restyled `ds-*` content
3. Admin Reward Campaigns — toolbar, table, badges, empty state

## Screenshots

| Shot | Path |
| --- | --- |
| Admin before (Lux) | `docs/theme-investigation/screenshots/v2/admin-dashboard-before-lux.png` |
| Admin after desktop | `docs/theme-investigation/screenshots/v2/admin-dashboard-desktop.png` |
| Admin tablet | `docs/theme-investigation/screenshots/v2/admin-dashboard-tablet.png` |
| Admin mobile | `docs/theme-investigation/screenshots/v2/admin-dashboard-mobile.png` |
| Member after | `docs/theme-investigation/screenshots/v2/member-dashboard-desktop.png` |
| Rewards after | `docs/theme-investigation/screenshots/v2/admin-rewards-desktop.png` |

## Validation

- Live v2 pages load `panel-*.css` + `panel-v2-*.css` / `panel-v2-*.js` only (no lux assets).
- Vite production build succeeded; manifest includes `panel-v2`.
- Lux CSS hashes unchanged (`lux-shell-DvuYHv42`, `lux-pages-CZLoIra6`).
- `register` CSS hash unchanged.
- `site` / `auth-portal` rebuild hashes changed (Tailwind pipeline; **source public Blade/CSS not edited**).
- Tests: full suite **648 passed / 1 failed** on first run (`MenuAuditTest` empty-state period). Fixed and re-run: **MenuAuditTest 2 passed**. PanelThemeTest 3 passed.
- Public homepage / login / register Blade & CSS sources untouched.
- No finance/auth/DB migrations in this work.

## Remaining pages (shell inherits v2; content not fully migrated)

All other Admin/Member pages get the new shell when `PANEL_THEME=v2`, but may still contain page-level Lux leftovers (`lh-card`, etc.) until migrated after pilot approval.
