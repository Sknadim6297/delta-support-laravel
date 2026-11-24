# Panel v2 Theme Cutover

## Feature flag

Server-only environment variable (never a public/user toggle):

```env
PANEL_THEME=v2   # new shell
PANEL_THEME=lux  # legacy Lux rollback (default in .env.example)
```

Config: `config/deltasupport.php` → `panel_theme`  
Helper: `App\Support\PanelTheme`

## Rollback

1. Set `PANEL_THEME=lux` in `.env`
2. Run `php artisan config:clear`
3. Reload Admin/Member panels — Lux assets and markup return

## When v2 is active

- Loads `panel.css` + `panel-v2.css` + `panel.js` + `panel-v2.js`
- Does **not** load `lux-shell.css`, `lux-pages.css`, or `lux-shell.js`
- Renders `pv-*` shell (sidebar/header/footer), not `lh-*` / `wrapper`

## Pilot pages (content redesigned)

1. Admin Dashboard
2. Member Dashboard (shell + ds-* restyle)
3. Admin Reward Campaigns listing

## Remaining pages

All other Admin/Member pages inherit the v2 **shell** when the flag is on, but page-level Lux leftovers (`lh-card`, etc.) may still appear until migrated.
