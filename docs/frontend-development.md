# Frontend development workflow

From the project root, install the locked Node dependencies once (and again only after dependency changes):

```powershell
npm ci
```

Run Laravel and Vite in separate terminals:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Keep the Vite process running while developing. CSS and JavaScript changes under `resources/` are served with HMR; Blade view changes trigger a browser refresh through the Laravel Vite plugin. You do not need to run `npm run build` after each local UI change. Dashboard styles are loaded through the dashboard layouts, and page-specific styles are pushed through their `@stack('styles')`.

Admin/Member panel CSS is modular. The Vite entry `resources/css/panel.css` is an import barrel only. Edit modules under `resources/css/panel/` (tokens, layout, components, responsive, content-overrides) and feature sheets under `resources/css/features/` (`dashboard.css`, `packages.css`, `rewards.css`). Do not put public website styles into these panel modules. `rewards.css` remains a separate Vite entry for page-scoped loading; dashboard/packages feature sheets are imported by `panel.css` so existing pages keep the same cascade without extra Blade entries.

Panel shell theme is controlled by server-only `PANEL_THEME` (`lux` legacy rollback, `v2` new design system). When `v2`, layouts load `resources/css/panel-v2.css` and `resources/js/panel-v2.js` and do not load Lux shell/pages assets. See `docs/theme-investigation/panel-v2-cutover.md`.

Files served directly from `public/` (such as images and standalone CSS) do not need a Vite build. Changes to Vite configuration or dependencies require restarting the dev server.

Run `npm run build` when producing release assets. Deploy `public/build/manifest.json` and the matching files under `public/build/assets/` together. When the Vite server is stopped, Laravel's `@vite` directives use that manifest as long as `public/hot` is absent. If a stale hot file remains after an interrupted dev session, confirm no Vite process is running and remove only `public/hot`; never deploy it. The Docker asset stage uses `npm ci` and copies the imported `public/site/css/` sources; Docker is not required for local development or Plesk deployments.
