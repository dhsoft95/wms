---
name: WMS Project State
description: Current completion state of the WMS project — what's done, what's pending
type: project
---

Full UI shell complete with glassmorphism app shell; 22 Livewire pages, 12 Form Objects, routes, CSS design system. Back-end (models/DB) not started.

Auth + app-shell glassmorphism complete (2026-10-08). All pages use the same glass design system.

**Why:** Ongoing build-out of a full WMS product on Laravel 11 + Livewire 3 + Flux + Tailwind v4.

**How to apply:** Back-end work is the next phase. Both auth pages and the main app shell are styled and ready.

## What is done

- App shell glassmorphism (Phase 3, 2026-10-08):
  - Background layer: WebP warehouse image (136KB), pre-blurred, fixed behind all content
  - CSS tiers: `glass-nav/glass-nav-dark`, `glass-solid/glass-solid-dark`, `glass-input-dark` added to `app.css`
  - All component classes updated: `.card` → glass, `.sidebar-link.active` → glass-brand, `.tabs` → glass-input track, `.btn-primary` → gradient glow, `.table` → glass borders, toasts → glass with colored border
  - `.card:has(table)` auto-upgrades to glass-solid (no template changes needed per page)
  - `config/wms.php`: `app_background` key added, `logo` defaults to `images/logo/logo.png`
  - Dark mode persistence fixed: `livewire:navigated` listener re-applies class from localStorage
  - Real logo shown in sidebar brand block when `public/images/logo/logo.png` exists
  - Scanner layout (`/scan/*`) unchanged — solid surfaces for handheld outdoor use
- App shell (sidebar, header, dark mode, toasts)
- 22 Livewire page components with placeholder data
- 12 Form Objects (save() stubs with TODO persist)
- Custom design system: `resources/css/app.css`
- Auth pages — glassmorphism redesign (Phase 2, 2026-10-07):
  - Full glass layout, branding config, 10 auth components, all 7 auth views rewritten

## What is next (back-end)

1. Eloquent models + migrations
2. Wire Form Object `save()` methods to real DB operations
3. Replace placeholder arrays with paginated Eloquent queries
4. Roles & permissions (Spatie)
5. File uploads (POD documents, GRN attachments)
6. PDF generation (gate pass, GRN print)
