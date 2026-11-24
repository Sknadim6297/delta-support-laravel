# Delta Support — Panel Theme Investigation Report

**Status:** Investigation complete. **No permanent theme replacement performed.** Awaiting approval before any redesign work.

**Scope:** Admin Panel + Member Panel only. Public website / login / registration untouched.

**Date:** 2026-11-24 (local)

**Screenshots:**
- Baseline (Admin Lux): `docs/theme-investigation/screenshots/01-admin-baseline-lux.png`
- Probe theme (Member): `docs/theme-investigation/screenshots/02-member-probe-theme.png`

---

## 1. Exact root cause — why Phase 1/2 did not change the dashboard look

The Admin and Member panels still look like the old theme because **the live DOM is the Lux shell**, not the Phase 2 `panel/layout.css` shell.

| Layer | What actually renders | What Phase 1/2 changed |
| --- | --- | --- |
| Chrome (sidebar, header, page bg, footer, collapse) | Lux Blade + `lux-shell.css` + `lux-shell.js` | **Nothing** — still `@vite` lux entries |
| Page chrome cards / KPI / native lux pages | `lux-pages.css` targeting `.lh-card`, `.lh-native` | **Nothing** |
| In-page forms / buttons / tables | `panel.css` under `.ds-content` | Modularized / reorganized only |
| Orphan shell | `#dsSidebar`, `.ds-app`, `.ds-header` in `panel/layout.css` | Present in CSS, **absent from every Blade view** |

Browser proof (`Runtime.evaluate` on `/dashboard`):

- `hasLuxShellInDom`: `wrapper`, `sidebar`, `header`, `main`, `pageTitle` → **true**
- `hasDsShellInDom` (`#dsSidebar` / `.ds-app` / `.ds-header`) → **false**

Disabling only the Lux stylesheets left `panel-*.css` loaded and **collapsed the shell** (sidebar transparent, width ~full viewport, header `position: static`). Panel CSS does not style `.lh-sidebar`.

**Conclusion:** Asset reorganization cannot change appearance while layouts still emit `main.wrapper.sb-default`, `.lh-sidebar`, `.lh-header.ds-lux-bar`, and load `lux-shell` / `lux-pages` **after** `panel.css`.

---

## 2. Rendering pipeline (evidence-backed)

```
Route (auth admin|member)
  → view under admin/* or member/*
  → @extends layouts
       admin: resources/views/admin/layouts/app.blade.php
       member: resources/views/member/layouts/app.blade.php
  → head @vite order:
       panel.css → lux-shell.css → lux-pages.css → panel.js → lux-shell.js
  → @include partials.brand-tokens  (inline #ds-brand-tokens :root)
  → body[data-lh-mode="light"]
  → <main class="wrapper sb-default">
       partials/lux-bar.blade.php     → header.lh-header.ds-lux-bar
       admin|member/partials/sidebar  → aside.lh-sidebar + x-navigation.lux-*
       .lh-main-content → .lh-page-title → .ds-content → @yield('content')
       <footer>
  → lux-shell.js binds: .lh-toggle-sidebar, .lh-drop-toggle, [data-lux-drop], sb-collapse/sb-default
```

### Layout file anchors

**Admin** — `resources/views/admin/layouts/app.blade.php`

- L13: `@vite(['resources/css/panel.css', 'resources/css/lux-shell.css', 'resources/css/lux-pages.css', 'resources/js/panel.js', 'resources/js/lux-shell.js'])`
- L19–20: `body data-lh-mode="light"` / `main.wrapper.sb-default`
- L21–30: `@include('partials.lux-bar', …)`
- L32: `@include('admin.partials.sidebar')`
- L34–45: `.lh-main-content` / `.lh-page-title` / `.ds-content`

**Member** — `resources/views/member/layouts/app.blade.php` — same Vite stack and Lux structure (L13–54).

**Header** — `resources/views/partials/lux-bar.blade.php`

- L4: `<header class="lh-header ds-lux-bar">`
- L7: `.lh-toggle-sidebar`
- L28–40: `[data-lux-drop]` menus

**Sidebar** — `resources/views/admin/partials/sidebar.blade.php` (Member mirror exists)

- L1–2: `.lh-sidebar-overlay` / `aside.lh-sidebar`
- L3–6: `.lh-sb-logo` / `.sb-full` / `.sb-collapse`
- L19+: `<x-navigation.lux-group>` / `<x-navigation.lux-link>`

**Navigation components**

- `resources/views/components/navigation/lux-group.blade.php` — `.lh-sb-item`, `.lh-drop-toggle`, `.lh-sb-drop`
- `resources/views/components/navigation/lux-link.blade.php` — Lux list links

### Vite / build verification (live browser)

Loaded on Admin dashboard:

| Asset | Hash (build) |
| --- | --- |
| `panel-BH4h7oIp.css` | current |
| `lux-shell-DvuYHv42.css` | current |
| `lux-pages-CZLoIra6.css` | current |
| `panel-Cu8PqnOA.js` | current |
| `lux-shell-BYXVsiIO.js` | current |

Member also loaded `rewards-BRmfivUz.css` (feature entry). Manifest entries match `public/build/manifest.json` → **browser is on latest built Vite assets**, not a stale Lux-only cache.

Brand tokens: inline `<style id="ds-brand-tokens">` sets `--ds-primary: #2563eb` (observed). Lux shell colors are mostly hard-coded in `lux-shell.css`, not driven by `--ds-*` shell tokens (`--ds-sidebar-bg` was empty in computed `:root`).

---

## 3. Legacy theme dependency map

### Lux-owned (must migrate or replace to change look)

| File | Role | Safe to delete today? |
| --- | --- | --- |
| `resources/css/lux-shell.css` (~910 lines) | Sidebar, header, wrapper, footer, mobile overlay, collapse | **No** — sole shell styler |
| `resources/css/lux-pages.css` (~579 lines) | `.lh-card`, dashboard native chrome | **No** — KPI/card chrome |
| `resources/js/lux-shell.js` | Collapse + dropdowns on Lux selectors | **No** — until JS rewritten |
| `resources/views/partials/lux-bar.blade.php` | Header DOM | **No** |
| `resources/views/admin/partials/sidebar.blade.php` | Admin nav DOM | **No** |
| `resources/views/member/partials/sidebar.blade.php` | Member nav DOM | **No** |
| `resources/views/components/navigation/lux-group.blade.php` | Accordion nav | **No** |
| `resources/views/components/navigation/lux-link.blade.php` | Nav links | **No** |
| Admin/Member `layouts/app.blade.php` Lux markup | Master shell | **No** |

### Dual-use / keep during redesign

| File | Role |
| --- | --- |
| `resources/css/panel.css` + `panel/**` + `features/**` | Content design system (`.ds-*`) already used in pages |
| `resources/views/components/ui/*` | Blade UI primitives (`ds-btn`, alerts, etc.) |
| `resources/js/panel.js` | Panel behaviors (non-shell) |
| `partials/brand-tokens` / `BrandTokens` | Runtime primary colors |
| Routes, controllers, permissions, finance | Must stay |

### Orphan / dead for shell (Phase 2 leftover)

| Selector / file | Evidence |
| --- | --- |
| `#dsSidebar`, `.ds-app`, `.ds-header` in `resources/css/panel/layout.css` L3 / L13 / L249 | **Zero** Blade matches for `#dsSidebar`; live DOM `hasDsShellInDom: false` |
| These rules | Do not affect current UI; safe to replace when building a real new shell |

### Actively used Lux classes (DOM + JS)

`wrapper`, `sb-default`, `sb-collapse`, `lh-sidebar`, `lh-sidebar-overlay`, `lh-sb-logo`, `sb-full`, `sb-collapse` (logo variant), `lh-sb-wrapper`, `lh-sb-list`, `lh-sb-item`, `lh-sb-title`, `lh-drop-toggle`, `lh-sb-drop`, `lh-page-link`, `condense`, `load-active`, `active`, `lh-header`, `ds-lux-bar`, `ds-lux-*`, `lh-toggle-sidebar`, `lh-main-content`, `lh-page-title`, `lh-breadcrumb`, `lh-card`, `lh-data`, `lh-native`, `data-lux-drop`, `data-lux-drop-button`

### JS selector lock-in (`resources/js/lux-shell.js`)

- L2–11: `main.wrapper`, `.lh-toggle-sidebar`, toggles `sb-collapse` / `sb-default`
- L26–35: `.lh-drop-toggle` accordion
- L39–62: `[data-lux-drop]` / `[data-lux-drop-button]` / `.is-open`

Any shell replacement must reimplement these behaviors or keep temporary compatibility shims.

### Cascade order (why Lux wins chrome)

1. `panel.css` (including unused `#dsSidebar` rules)
2. **`lux-shell.css`** — wins body / sidebar / header / main / footer
3. **`lux-pages.css`** — wins `.lh-card` / KPI chrome
4. Inline brand tokens
5. Page `@stack('styles')`

Content controls use `:where(.wrapper .ds-content) …` in panel modules → **specificity 0 for `:where`**, so they style in-page widgets but never override Lux shell.

---

## 4. Browser computed-style evidence

### Admin `/dashboard` (authenticated)

| Component | Computed highlight | Winning rule (last match) | Sheet |
| --- | --- | --- | --- |
| `body` | bg `rgb(244,246,249)` | `body[data-lh-mode]` | lux-shell |
| `.wrapper` | bg `rgb(245,247,251)` | `.wrapper, .wrapper footer` | lux-shell |
| `.lh-sidebar` | bg `rgb(16,27,45)`, fixed, width 256px | `.wrapper .lh-sidebar` | lux-shell (~L515) |
| `.lh-sb-logo` | white, radius 12px | `.wrapper .lh-sidebar .lh-sb-logo` | lux-shell (~L53) |
| `.lh-header.ds-lux-bar` | white, fixed, h 64px, soft shadow | `.wrapper .ds-lux-bar.lh-header` | lux-shell (~L110) |
| `.lh-main-content` | bg `rgb(245,247,251)`, pad-top 64px | `.wrapper .lh-main-content` | lux-shell (~L338) |
| `h1.ds-lux-title` | color `#0f172a`, 18px | `.wrapper .ds-lux-title` | lux-shell |
| `.lh-card.ds-kpi` | white, radius 12px, soft shadow | `.wrapper .ds-content.lh-native .ds-dash .ds-kpi` / `.wrapper .lh-card` | lux-pages |
| `table.lh-data` | white, 13px | panel table rules under `.ds-content` | panel |
| `footer` | bg `rgb(245,247,251)` | `.wrapper, .wrapper footer` | lux-shell |

### Admin `/admin/packages/create` (forms / buttons)

| Component | Computed | Winning sheet |
| --- | --- | --- |
| `button.ds-btn` (“Create package”) | bg `#2563eb`, white text, radius 10px | **panel** (`:where(.wrapper .ds-content) .ds-btn…`) |
| Form inputs | white bg, radius 10px, border `#d8e2ee` | **panel** (forms + packages feature) |

### Member `/member-panel/ST100000`

Same Lux shell stack and classes (`wrapper sb-default`, `.lh-sidebar` bg `rgb(16,27,45)`, header white). Shell is shared; only sidebar nav items differ.

### Lux-off experiment

Disabled `lux-shell-*.css` + `lux-pages-*.css` with `panel-*.css` still on:

- Sidebar bg → transparent; width → ~789px (layout destroyed)
- Header → `position: static`, transparent

**Proof:** shell appearance is Lux-owned; modular panel CSS is not a second theme for chrome.

---

## 5. Controlled theme replacement experiment (reversible)

**Method:** Runtime-only CSS injection (`#theme-probe-experiment`). No production theme files permanently modified. Injection removed on navigation; no leftover experiment files in `resources/`.

**Targets:** Lux selectors (`.lh-sidebar`, `.ds-lux-bar.lh-header`, `.ds-lux-title`, `.lh-card`, page background).

**Observed after-state (Member dashboard CDP):**

| Property | Before | After probe |
| --- | --- | --- |
| Sidebar | `rgb(16,27,45)` | emerald gradient |
| Header | `rgb(255,255,255)` h64 | `rgb(17,24,39)` h72 + green border |
| Title | slate 18px | mint `rgb(110,231,183)` 28px |
| Page bg | light gray | `#0b1220` |

**Screenshots:** baseline Admin Lux vs Member probe theme under `docs/theme-investigation/screenshots/`.

**Finding:** A new visual theme **can** apply if it targets the **actual Lux DOM** (or if Blade/JS are replaced). Editing only `panel/layout.css` / tokens **cannot** change sidebar/header because those nodes never use `#dsSidebar`.

---

## 6. Option comparison

| Criteria | A: Refactor Lux | B: Replace Lux shell + new DS | C: Isolated new theme + incremental migrate |
| --- | --- | --- | --- |
| Design flexibility | Low (lh-* vocabulary) | **High** | High after cutover; medium while dual |
| Maintainability | Poor (legacy names) | **Good** if one shell | Temporary dual CSS/JS cost |
| Legacy deps | Keeps all | Removes shell deps | Gradual |
| Complexity | Medium (paint over) | Medium–high (layouts + JS) | Highest calendar time |
| Regression risk | Medium (specificity wars) | Concentrated at shell cutover | Lower per page; longer dual risk |
| Responsive | Must re-test Lux breakpoints | Rebuild intentionally | Dual breakpoints until done |
| DX long-term | Weak | **Strong** | Strong after migration ends |

### Recommendation: **Option B (shell replacement), executed with Option C’s cutover discipline**

Evidence says the blocker is the **Lux Blade + lux-shell/pages + lux-shell.js triangle**, not missing panel CSS modules.

Recommended path:

1. **Do not** keep painting Lux forever (reject pure A).
2. **Do not** run a long dual-theme era across every page (pure C is slow and invites drift).
3. **Replace shared shell once** (layouts, header, sidebars, nav components, shell CSS/JS) with a new premium dashboard system aligned to existing `.ds-*` content components.
4. Keep page bodies / routes / `x-ui.*` / feature CSS; restyle content tokens after shell lands.
5. Feature-flag or env gate the new shell locally until approved for default.

This maximizes design freedom while preserving business functionality and limiting “two themes forever.”

---

## 7. Files that can be replaced vs must be migrated

### Replaceable after new shell ships (Lux)

- `lux-shell.css`, `lux-pages.css`, `lux-shell.js`
- `partials/lux-bar.blade.php`
- `navigation/lux-group.blade.php`, `navigation/lux-link.blade.php`
- Lux class markup in both panel layouts + both sidebars
- Orphan `#dsSidebar` / `.ds-app` rules in `panel/layout.css` (replace with real new shell selectors)

### Preserve / migrate carefully

- All `routes/web.php` panel routes and middleware
- Sidebar **link lists** (routes, labels, `routeIs` active states, permissions)
- `components/ui/*` and page Blade under `admin/` / `member/`
- `panel/components/*`, `features/*`, brand tokens
- Financial / ROI / wallet / rewards **logic** (no theme work there)
- Public `site` / auth-portal / register assets (out of scope)

---

## 8. Implementation roadmap (approval-gated)

**Do not start until this report is approved.**

### Phase T0 — Guardrails

- Confirm public site fingerprints unchanged
- Local-only; no deploy; no production migrations
- Preserve unrelated uncommitted work

### Phase T1 — New shell design system (no page rewrite)

- New tokens: canvas, sidebar, header, elevation, radius, type
- New Blade: `layouts/panel.blade.php` (or replace both app layouts), `panel/header`, `panel/sidebar`, `panel/nav-group|link`
- New CSS entry e.g. `resources/css/panel-shell.css` (not Lux)
- New JS for collapse / mobile / dropdowns on new selectors
- Feature flag: `PANEL_SHELL=lux|next` (default lux until signed off)

### Phase T2 — Wire Admin + Member behind flag

- Point layouts to new shell when flag=next
- Port nav items 1:1 from existing sidebars
- Visual QA: dashboard, packages form, members table, rewards, mobile

### Phase T3 — Content polish

- Align `.ds-btn` / cards / tables / modals to new tokens
- Remove lux-pages dependencies from dashboard views (`lh-card` → ds components)
- Drop Lux Vite entries when flag flips permanently

### Phase T4 — Cleanup

- Delete lux-shell/pages/js and lux Blade/nav
- Delete orphan `#dsSidebar` layout CSS
- Update `docs/frontend-development.md`
- Regression pass (manual + existing PHPUnit; no finance logic changes)

### Explicit non-goals

- Public website redesign
- Auth/login/register redesign
- Financial engine / ROI / ledger / eligibility changes

---

## 9. Answers checklist

1. **Root cause:** Live markup + CSS/JS are Lux; Phase 1/2 only modularized parallel `panel` CSS that does not own the shell.
2. **Dependency tree:** Layouts → lux-bar + sidebars + lux-nav → lux-shell.css/js + lux-pages.css; content pages use `.ds-*` under `.ds-content`.
3. **Computed styles:** Documented in §4 with selectors and sheets.
4. **Cascade:** panel → **lux-shell → lux-pages** → brand tokens; Lux wins chrome.
5. **Vite:** Live hashes match current `public/build` manifest.
6. **Experiment screenshots:** `docs/theme-investigation/screenshots/` (baseline + probe).
7. **Safe to replace (later):** Lux CSS/JS/Blade shell + orphan `#dsSidebar` rules.
8. **Must preserve/migrate:** Routes, nav items, permissions, `.ds-*` page UI, finance, public site.
9. **Strategy:** Option B with flagged cutover (C discipline).
10. **Roadmap:** §8 — awaiting approval before permanent replacement.

---

## Approval gate

**No permanent theme replacement has been started.**  
Approve this report (and Option B cutover) to begin Phase T1.
