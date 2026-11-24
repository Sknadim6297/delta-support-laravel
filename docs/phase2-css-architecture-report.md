# Phase 2 — Modular Admin & Member Panel CSS Architecture

Date: 2026-11-24  
Scope: Panel CSS modularization only. Public website protected.  
Phase 3: **Not started.**

## Before / after architecture

### Before
```text
resources/css/panel.css          (~4k lines, monolithic)
resources/css/lux-shell.css
resources/css/lux-pages.css
resources/css/features/rewards.css
resources/css/tokens.css         (shared with public — unchanged)
resources/css/member-module.css
```

### After
```text
resources/css/panel.css                 # import barrel only
resources/css/panel/
├── tokens.css                          # panel --color-* aliases
├── layout.css                          # shell / sidebar / header
├── responsive.css                      # shell breakpoints + print
├── content-overrides.css               # low-specificity wrapper defaults
└── components/
    ├── buttons.css
    ├── badges.css
    ├── cards.css
    ├── alerts.css
    ├── forms.css
    ├── tables.css                      # includes pagination / loading
    └── modals.css
resources/css/features/
├── rewards.css                         # separate Vite entry (unchanged role)
├── dashboard.css                       # ds-dash* (imported by panel.css)
└── packages.css                        # package catalog + admin form
```

`lux-shell.css` / `lux-pages.css` kept as existing shell companions (not redesigned).  
`tokens.css` (shared) and public CSS entries were not modified.

## Files created / modified

**Created**
- `resources/css/panel/**` modules listed above
- `resources/css/features/dashboard.css`
- `resources/css/features/packages.css`
- `docs/phase2-css-architecture-report.md`

**Modified**
- `resources/css/panel.css` → barrel imports (cascade order preserved)
- `docs/frontend-development.md` → panel module workflow notes
- Regenerated `public/build/*` panel asset hash only

**Not modified (protected)**
- `site.css`, `auth-portal.css`, `register.css`, `public/site/`
- MoneyDisplay, financial services, migrations, auth logic

## Duplicate CSS removed

No aggressive deletion of uncertain overlapping rules between `panel` and `lux-*`.  
Phase 2 focused on **physical modularization with identical cascade**, not visual redesign.

Verified split boundaries so component rules are not duplicated across the new module files (alerts/forms cut fixed after an initial overlap).

## Remaining legacy selectors

- Large `panel/content-overrides.css` still holds low-specificity `.wrapper .ds-content …` utilities (switches, finance grids, password toggle, pagination chrome, etc.).
- `lux-pages.css` / `lux-shell.css` still parallel some panel content patterns (`:where(.wrapper …)`).
- Historical indentation / formerly nested selectors retained as-is inside modules.
- `member-module.css` remains a separate import (member-management feature cluster).

## CSS bundle sizes

| Asset | Before | After |
| --- | ---: | ---: |
| Panel CSS | `panel-5sRBwb0F.css` **79,400** bytes | `panel-BH4h7oIp.css` **79,400** bytes |
| Rewards CSS | `rewards-BRmfivUz.css` 3,771 | **unchanged** |
| Site CSS | `site-B0Fav1HB.css` 62,625 | **unchanged** |
| Auth portal | `auth-portal-B5n2_HYj.css` 45,841 | **unchanged** |
| Register | `register-BBxcR53B.css` 6,628 | **unchanged** |

Panel gzip ≈ 13.10 → 13.18 KB (content-equivalent rebuild).

## Build & tests

- `npm run build`: success; `public/hot` absent
- Manifest includes `resources/css/features/rewards.css`
- Full suite: **646 passed / 0 failed** (6579 assertions)

## Public asset fingerprint verification

| Entry | Fingerprint | Result |
| --- | --- | --- |
| `resources/css/site.css` | `site-B0Fav1HB.css` | SAME |
| `resources/css/auth-portal.css` | `auth-portal-B5n2_HYj.css` | SAME |
| `resources/css/register.css` | `register-BBxcR53B.css` | SAME |
| `resources/js/site.js` | `site-Bqe3Ocop.js` | SAME |

Runtime check: `/login` loads `auth-portal-B5n2_HYj.css`; `/` loads `site-B0Fav1HB.css` + `site-Bqe3Ocop.js`.

## Visual regression (local isolated admin session)

Checked Admin Dashboard, Packages list, Rewards list — desktop and tablet (~768).  
Sidebar/header/cards/tables/buttons appear consistent with pre-Phase-2 panel theme.  
No `!important` added in new modules.  
Browser loaded `panel-BH4h7oIp.css` + lux shell/pages as expected.

## Independent customization notes

- **Rewards**: still page-scoped via `@vite('resources/css/features/rewards.css')` — changing popup styles does not require editing layout/components modules.
- **Dashboard / Packages**: source lives under `features/` for maintainability; still imported by `panel.css` so existing pages keep prior cascade without new Blade entries. Optional future step (Phase 3+): promote them to separate Vite entries like Rewards if stricter page isolation is required.

## Blockers

**None.**

## Phase 2 status

**COMPLETE** (architecture cleanup only; no public redesign; no deploy; Phase 3 not started).
