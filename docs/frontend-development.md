# Frontend development

## Local Vite

```bash
npm install
npm run dev
```

Keep Apache/XAMPP serving `public/`. Vite HMR updates CSS/JS under `resources/`.

Production assets:

```bash
npm run build
```

## Panel shell (Admin / Member)

Panel V2 is the only supported Admin/Member shell (`pv-app`, `panel-v2.css`, `panel-v2.js`).

- Entry CSS: `resources/css/panel.css` (shared components) + `resources/css/panel-v2.css` (shell/grids)
- Feature CSS (on demand): `resources/css/features/rewards.css`, packages, dashboard
- Legacy Lux theme was removed in T4. Rollback: `git checkout t4-pre-legacy-removal`

## Public site

Do not import panel CSS into public pages. Public entries remain:

- `resources/css/site.css`
- `resources/css/auth-portal.css`
- `resources/css/register.css`
- `resources/js/site.js`

See `docs/theme-investigation/panel-v2-final-report.md` after T4 verification.
