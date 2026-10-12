# WMS — Project Brief

> **Developer conventions:** See [`docs/BUILD-CONVENTIONS.md`](docs/BUILD-CONVENTIONS.md) for coding standards, VW scoping rules, and naming conventions that must be followed in all modules.

## What this is

A Warehouse Management System UI shell built on **Laravel 11 + Livewire 3 + Flux + Alpine.js + Tailwind CSS v4**.
The UI is complete and fully navigable. No database models or migrations exist yet — all data is placeholder arrays.

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Reactive UI | Livewire 3 (class-based components + Form Objects) |
| Component library | Flux (used only for auth pages; custom shell elsewhere) |
| CSS | Tailwind CSS v4 via `@tailwindcss/vite` (no `tailwind.config.js`) |
| JS | Alpine.js (sidebar, dark mode, toasts, dropdowns) |
| Build | Vite 6 |
| App font | Inter Variable (self-hosted via `@fontsource-variable/inter`) |
| Mono font | JetBrains Mono Variable (self-hosted via `@fontsource-variable/jetbrains-mono`) |
| Auth font | Sora (Google Fonts, auth pages only) |

---

## Custom design system

Defined in `resources/css/app.css` using Tailwind v4's `@theme` + `@layer components`.

### Simba colour palette

| Token | Hex | Use |
|---|---|---|
| `simba-50` | `#fdf2f2` | Backgrounds, badge fills |
| `simba-600` | `#a22631` | Primary buttons, active states |
| `simba-900` | `#5c0e14` | Hover states |
| `simba-950` | `#350b10` | Dark sidebar |

### Component classes (all in `@layer components`)

`.card`, `.card-header`, `.card-body`, `.card-footer`, `.label`, `.input`, `.input-sm`, `.hint`,
`.btn`, `.btn-sm`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-success`, `.btn-danger`,
`.icon-btn`, `.badge` + colour variants, `.dot`, `.table`, `.mono`, `.stat-number`,
`.page-header`, `.page-title`, `.page-sub`, `.sidebar-link` (+ `.active`), `.tabs`, `.tab` (+ `.active`),
`.kpi-icon`, `.dropdown-item`, `.list-row`, `.alert-info`, `.alert-warn`, `.radio-card`, `.dropzone`,
`.progress`, `.section-label`

### Type-scale `@utility` classes (app shell only)

`text-page-title` (24/32 600 -0.02em), `text-card-title` (15/22 600),
`text-table-head` (12/16 500 uppercase 0.04em), `text-body` (14/20 400),
`text-meta` (12/16 400), `text-stat` (30/36 700 tabular -0.02em)

---

## Layout shell

`resources/views/components/layouts/app/sidebar.blade.php`

- Replaces Flux's default sidebar entirely (auth pages are untouched)
- `<html x-data="{ sidebarOpen, sidebarRail, darkMode, commandOpen, openGroups, … }">` — all nav state persisted in `localStorage` via Alpine `$persist`
- Navigation built by `NavigationBuilder` from `config/navigation.php` (single source of truth)
- Active state via `request()->routeIs($pattern)` — supports wildcard patterns e.g. `inbound.containers.*`
- `<livewire:toast />` injected at the bottom of every page
- `<x-nav.command-palette>` injected globally (open with Ctrl/Cmd+K or clicking the search bar)

### Navigation system (completed 2026-10-09)

| File | Role |
|---|---|
| `config/navigation.php` | Single source of truth — groups → items with `key`, `label`, `icon`, `route`, `active_routes`, `can`, optional `badge` |
| `app/Support/Navigation/NavigationBadges.php` | Badge count service — `get(string $key): ?int`; stub returns `null` until models exist |
| `app/Support/Navigation/NavigationBuilder.php` | Filters groups/items by auth `can()`, resolves badges, resolves URLs, returns `is_active` and `has_active` |
| `resources/views/components/nav/group.blade.php` | Collapsible group header + items; `x-collapse` animation; state persisted via `$persist` |
| `resources/views/components/nav/item.blade.php` | Single nav link; icon opacity + active class; `title` tooltip in rail mode |
| `resources/views/components/nav/badge.blade.php` | Count badge pill; hidden when count is null |
| `resources/views/components/nav/command-palette.blade.php` | Cmd+K command palette with live search across all nav items |

**Alpine state on `<html>`:**
- `sidebarRail` (`$persist`) — collapsed 72px icon-only rail vs 256px expanded
- `openGroups` (`$persist`) — per-group collapse state (default: all open)
- `commandOpen` — command palette visibility
- `toggleGroup(key)`, `expandAllGroups()`, `collapseAllGroups(keys)` — group helpers

**Keyboard shortcuts:**
- `[` — toggle rail mode (when focus not in input/textarea)
- `Ctrl/Cmd+K` — open command palette

**Rail mode:** sidebar collapses to 72px, group labels hidden, items show icon-only with `title` tooltip, group divider lines separate sections.

**Footer zone:** Expand all / Collapse all buttons, collapse-rail toggle button, version number.

### Toast system

- `app/Livewire/Toast.php` + `resources/views/livewire/toast.blade.php`
- Trigger from any Livewire component: `$this->dispatch('toast', message: '...', type: 'success|error|info')`

### Icon system

`resources/views/components/ui/icon.blade.php` — `<x-ui.icon name="..." class="..." />`
42 SVG icons stored as a PHP paths array. Supported names:
`archive`, `arrows`, `badge-check`, `bell`, `chart-bar`, `check`, `chevron-double-left`, `chevron-double-right`, `chevron-down`, `chevron-right`, `clipboard`, `clock`, `cog`, `download`, `flag`, `inbox`, `list`, `logout`, `menu`, `moon`, `package`,
`plus`, `printer`, `search`, `shield`, `squares-2x2`, `sun`, `trash`, `truck`, `upload`, `user`, `users`,
`warning`, `x`, `eye`, `home`, `adjustments`, `lock`, `refresh`

---

## Screens (22 Livewire page components)

| Route | Component | Description |
|---|---|---|
| `/dashboard` | `Dashboard\Overview` | KPIs, pipeline table, utilisation bars, activity |
| `/approvals` | `Approvals\Index` | Tab-filtered approval queue; approve/reject |
| `/inbound/containers` | `Inbound\Containers\Index` | Container registry with search + status tabs |
| `/inbound/containers/create` | `Inbound\Containers\Create` | New container form + yard slot grid |
| `/inbound/grn` | `Inbound\Grn\Index` | GRN list with progress bars |
| `/inbound/grn/create` | `Inbound\Grn\Create` | GRN form with dynamic line items |
| `/inbound/inspections` | `Inbound\Inspections\Index` | Inspection form + recent list |
| `/warehouse/putaway` | `Warehouse\Putaway\Index` | RF handheld emulator + queue |
| `/warehouse/movements` | `Warehouse\Movements\Index` | Movement form + history |
| `/warehouse/transfers` | `Warehouse\Transfers\Index` | Inter-department transfer form |
| `/inventory/stock` | `Inventory\Stock\Index` | Stock table with search + warehouse filter |
| `/inventory/adjustments` | `Inventory\Adjustments\Index` | Adjustment form with live variance |
| `/inventory/counts` | `Inventory\Counts\Index` | Cycle count inline entry |
| `/outbound/orders` | `Outbound\Orders\Create` | Order form with dynamic lines |
| `/outbound/dispatch` | `Outbound\Dispatch\Index` | Dispatch form + gate pass preview |
| `/outbound/pod` | `Outbound\Pod\Index` | POD form + awaiting list |
| `/reports` | `Reports\Index` | 6 report cards with date range |
| `/reports/print` | `Reports\PrintDocument` | Printable GRN document |
| `/admin/skus` | `Admin\Skus\Index` | SKU master create form |
| `/admin/users` | `Admin\Users\Index` | Users table + modal |
| `/profile` | `Profile\Index` | Tabbed profile/password/preferences |

*(22nd route: `/approvals` listed above)*

---

## Form Objects (`app/Livewire/Forms/`)

`ContainerForm`, `GrnForm`, `InspectionForm`, `MovementForm`, `TransferForm`, `AdjustmentForm`,
`OrderForm`, `DispatchForm`, `PodForm`, `SkuForm`, `UserForm`, `ProfileForm`

All have:
- Public `#[Validate]` properties
- `save(): bool` — currently dispatches a toast and returns `true` (no DB writes)
- `// TODO: persist $this->all()` comment where real persistence goes

---

## Typography redesign — app shell (completed 2026-10-09)

Font swap applied to the authenticated app layout only. Auth pages unchanged.

- **App font**: Inter Variable (self-hosted), loaded via `@import '@fontsource-variable/inter/wght.css'`
- **Mono font**: JetBrains Mono Variable (self-hosted), loaded via `@import '@fontsource-variable/jetbrains-mono/wght.css'`
- **Auth font**: Sora — unchanged, `--font-sans` still points to Sora; auth `<body>` keeps `font-sans`
- App layout `<body>` now uses `font-app app-body`; `app-body` adds `font-feature-settings: 'cv11', 'ss01', 'tnum'` for tabular numbers and Inter OpenType alternates
- `--font-app`, `--font-auth`, `--font-mono` tokens added to `@theme`; `--font-mono` overrides Tailwind's default `font-mono` → all `.mono` usages automatically get JetBrains Mono
- All component classes updated to the type scale: `page-title` (24/32/600), `card-title` (15/22/600), `label` (13/18/500), `hint` (12/16/400), `section-label` (11/16/500 0.08em), `btn` (14/20/500), `badge` (12/16/500), `table thead th` (12/16/500 0.04em), `table tbody td` (14/20/400), `sidebar-link` (14/20/500), `mono` (13px/500)
- New `.stat-number` component class: 30/36/700 tabular -0.02em
- New type-scale `@utility` classes: `text-page-title`, `text-card-title`, `text-table-head`, `text-body`, `text-meta`, `text-stat`
- Sidebar group labels updated to 11px/500/0.08em tracking
- `npm run build` clean; font woff2 files bundled (inter-latin 48 kB, jetbrains-mono-latin 40 kB), no CDN requests

---

## App shell — Glassmorphism redesign (completed 2026-10-08)

Full glassmorphism design applied to the dashboard/app layout shell.

### New CSS utilities (`resources/css/app.css`)
Two new `@utility` tiers added alongside the existing four:
- `glass-nav` / `glass-nav-dark` — sidebar + topbar (white/55 · zinc-900/55, blur 20px)
- `glass-solid` / `glass-solid-dark` — tables, forms, dropdowns (white/85 · zinc-900/85, blur 16px)
- `glass-input-dark` — dark-mode counterpart for `glass-input`
- All utilities include `@supports not (backdrop-filter)` solid fallbacks
- `prefers-reduced-transparency: reduce` switches all glass to solid
- `prefers-reduced-motion: reduce` disables hover lifts

### Updated component classes
All `@layer components` classes updated to use glass tokens:
- `.card` → `glass rounded-2xl` with inner top highlight; `.card:has(table)` auto-upgrades to `glass-solid` tier (no template changes needed)
- `.sidebar-link.active` → `glass-brand` with white text + left accent bar
- `.tabs` → `glass-input` track; `.tab.active` → `glass` pill
- `.btn-primary` → brand gradient + glow shadow (same as auth)
- `.btn-secondary` → `glass-input` style
- `.badge-*` → translucent `/15` fill + `/30` ring
- `.table` → glass-based header bg (white/50) + row dividers (white/60)
- `.alert-info` / `.alert-warn` → `glass` with colored left border (3px)
- `.progress` → white/30 (dark: white/10) track
- Toast → `glass dark:glass-dark` with colored left border (was solid colored)

### App layout (`components/layouts/app/sidebar.blade.php`)
- Fixed background layer (`app-bg-layer`, `-z-10`, `scale-105 blur-md`) renders `app_background` image once for the whole shell
- Light mode overlay: white/72→55 gradient + faint brand tint corner
- Dark mode overlay: zinc-950/82→78 gradient + faint brand tint
- Sidebar: `glass-nav dark:glass-nav-dark`, white/40 right border (dark: white/10)
- Brand block: real logo image when `images/logo/logo.png` exists; initials `glass-brand` tile as fallback
- Mobile overlay: `bg-zinc-950/30` (was `bg-slate-950/60`)
- Topbar: `glass-nav dark:glass-nav-dark`, sticky, white/40 bottom border
- Topbar search: `glass-input dark:glass-input-dark`
- User dropdown: `glass-solid dark:glass-solid-dark rounded-2xl`
- Brand config (name, subtitle, initials) now read from `config('wms.branding')` instead of hardcoded

### Config + branding (`config/wms.php` + `.env.example`)
- Added `app_background` key (default: `images/app/warehouse-bg.webp`)
- Added `logo` default: `images/logo/logo.png`
- Background image: `public/images/app/warehouse-bg.webp` (136KB WebP, compressed from 1.8MB JPG)

### Dark mode persistence fix (`resources/views/partials/head.blade.php`)
Added `livewire:navigated` event listener so the `dark` class is re-applied from `localStorage` after every `wire:navigate`, preventing dark mode reset on SPA navigation.

### Exceptions (unchanged)
- `components/layouts/scan.blade.php` — scanner layout kept solid (no glass, no backdrop-filter)
- Print styles: `.app-bg-layer` hidden; all glass utilities render solid white
- `components/layouts/app/header.blade.php` — Flux-based settings layout, untouched

---

## Auth UI — Glassmorphism redesign (completed 2026-10-07)

Full glassmorphism auth design implemented per `auth-glassmorphism-prompt.md`.

### CSS design tokens + utilities (`resources/css/app.css`)
- Semantic brand aliases added to `@theme`: `--color-brand`, `--color-brand-hover`, `--color-brand-soft`
- Four `@utility` glass classes: `glass` (white/65 + blur/24), `glass-dark`, `glass-brand` (brand-tinted), `glass-input` (white/55 + blur/12)
- `@supports` fallback for no-backdrop-filter browsers (solid opaque backgrounds)

### Config + branding (`config/wms.php` + `.env.example`)
New `branding` keys: `name`, `subtitle`, `initials`, `logo`, `auth_background`, `headline`, `headline_accent`, `tagline`, `features` (array of `icon/title/text`), `footer_pillars`. All overridable via `.env`.

### Auth layout (`components/layouts/auth.blade.php`)
Full single-file layout replacing the prior delegation pattern. Features:
- Warehouse photo as fixed full-screen `<div>` with `scale-105` to hide blur edges
- Left brand gradient overlay (`simba-950/90 → transparent`)
- Three decorative diagonal glass shapes (corners, `backdrop-blur-sm`)
- **Left panel** (hidden below `lg`, 55% width): glass-brand initials tile, brand name + subtitle, large headline + `headline_accent` in `simba-200`, tagline, 2×2 feature grid (`x-auth.feature-tile`), footer pillars with shield icon
- **Right panel**: glass card (`glass dark:glass-dark`, `rounded-3xl`, diagonal sheen via gradient overlay), mobile brand block above card
- `auth/split.blade.php` now delegates back to auth.blade.php (backward compat)

### Auth form components (`resources/views/components/auth/`)
10 new components:
- `input` — glass-input field, leading icon slot (mail/lock/user/key), error message, `wire:model` passthrough
- `password` — glass-input + Alpine show/hide eye toggle, lock icon
- `button` — brand gradient, glow shadow, inner top highlight, `wire:loading` spinner, hover lift, `motion-reduce` support
- `checkbox` — glass-styled unchecked, brand-filled checked
- `divider` — white/50 lines with "or"
- `secure-note` — glass-input box with shield icon + brand name
- `link` — brand-colored semibold anchor
- `alert` — glass surface with colored left border for success/error/warning/info
- `method-toggle` — Alpine segmented control, `glass-input` track + `glass` active pill
- `feature-tile` — glass-brand tile with inline SVG icons (container, arrows-right-left, cube, chart-bar)

### Auth pages (7 of 8 updated)
All use `x-auth-header` (now has brand accent bar), new `auth/*` components:
- **login**: email icon, password with show/hide, inline "Forgot password?" anchor at label level, remember-me checkbox, brand button, divider, secure note
- **forgot-password**: email icon, brand button, back-to-sign-in link
- **reset-password**: read-only email, two password fields with show/hide, brand button
- **verify-email**: alert for sent status, brand button, log-out text link
- **confirm-password**: password with show/hide, brand button
- **two-factor-challenge**: glass-input segmented toggle (primary method / Recovery code), code input with key icon, brand button, email resend link
- **accept-invitation**: glass-input user info block (read-only), two password fields, brand button; expired link shows warning alert
- **register**: left unchanged (self-registration disabled; no UI links to it)

### `auth-header.blade.php`
Now includes brand accent bar (h-1 w-12 simba-600) above the title.

### Emails (unchanged from Phase 1)
- `html/themes/default.css`: button primary + panel border = `#a22631`; font stack includes `'DM Sans'`.
- `html/header.blade.php`: logo from config, SVG file, or initials fallback.

---

## Module 2 — Stock Ledger & Stock-on-Hand Engine (completed 2026-10-10)

### New tables (6 migrations)
`batches`, `lpns`, `stock_transactions`, `stock_movements`, `stock_balances`, `stock_reservations`

### New enums (`app/Enums/Inventory/`)
`StockStatus`, `MovementType` (12 types), `LpnStatus`, `ReservationStatus`

### New DTOs (`app/Data/Inventory/`)
`StockKey` (with `hash()` SHA-256 for MySQL NULL-safe uniqueness), `StockLine`, `StockPosting`, `StockReference`

### New models (`app/Models/Inventory/`)
`Batch`, `Lpn`, `StockTransaction` (immutable), `StockMovement` (immutable + BelongsToVirtualWarehouse), `StockBalance`, `StockReservation`

### Services
- `StockLedger` — single writer: `post()`, `receive()`, `move()`, `changeOwner()`, `changeStatus()`, `adjust()`, `openingBalance()`, `reverse()`
- `StockReservations` — `reserve()` (lockForUpdate), `release()` (partial OK)
- `StockQueryService` — all reads for Livewire; VW-scoped

### Artisan command
`php artisan wms:stock:verify [--fix]` — verifies balances against ledger; scheduled nightly at 02:00; notifies system admins on mismatch

### New screens
- `/inventory/stock` — Stock on Hand (real data, 8 filters, stats cards, low-stock highlights)
- `/inventory/movements` — Movement History (transaction list + detail drawer + reverse modal)
- `/inventory/opening-balance` — Opening Balance form (System Admin only)
- Item detail: **Stock tab** added (balances by location + last 20 movements)
- Location list: **View Stock** row action added

### Permissions added
`stock.view`, `stock.movements.view`, `stock.opening-balance`, `stock.reverse`, `stock.verify`

### Seeders
`StockDemoSeeder` — posts demo opening balances via `StockLedger::openingBalance()` only

### Tests
221 passing, 0 failures. 22 new inventory tests (ledger correctness, immutability, reservations, idempotency, reversal, UoM conversion, quarantine, VW scope, verify command). Arch test enabled.

### Manual test checklist
`docs/testing/module-02.md`

---

## Module 2B — Serial Number Tracking & Data Import (completed 2026-10-12)

### New tables (3 migrations)
`serial_numbers`, `stock_movement_serials`, `data_imports`

### New enums
| Enum | Values |
|---|---|
| `app/Enums/Inventory/SerialStatus` | `in_stock`, `dispatched`, `written_off` |
| `app/Enums/ImportStatus` | `uploaded`, `validating`, `validated`, `failed_validation`, `importing`, `completed`, `failed` |
| `app/Enums/ImportMode` | `create_only`, `create_or_update` |
| `app/Enums/ImportType` | `departments_vws`, `partners`, `item_categories`, `items`, `locations`, `opening_stock` |

### New models
- `app/Models/Inventory/SerialNumber` — tracks one physical unit forever; `normalize()` trims+uppercases; scopes `inStock()`, `forVirtualWarehouse()`
- `app/Models/Inventory/StockMovementSerial` — pivot between movements and serials (`$timestamps = false`)
- `app/Models/DataImport` — import record with enum casts, `hasErrors()`, `canConfirm()`, activity log

### StockLedger changes (`app/Services/Inventory/StockLedger.php`)
- `openingBalance()` now accepts `?string $idempotencyKey` for chunked opening-stock import
- `post()` pre-collects `$releasedInThisPost` — all serials on outbound lines in the same posting — so inbound lines of the same posting don't falsely reject them as "already in stock"
- Serial pre-validation added at step 5g2 (after `StockKey` build, before balance check): normalise, reject empty/whitespace, per-direction duplicate check, inbound conflict check (excluding `$releasedInThisPost`), outbound source-key verification (full 6-column: item + location + VW + status + batch + lpn)
- Serial status driven by `MovementType`: Dispatch → `dispatched`, Adjustment outbound → `written_off`, any inbound → `in_stock`, moves/hold/release → `in_stock` (position update only)
- After write: upsert serial rows (`first_received_at` set on insert, never overwritten); link to `StockMovement` via `stock_movement_serials`
- `reverse()` loads movements `->with('serials')` and passes serials back through the reversal `StockLine`

### Import architecture
**Base class** `app/Imports/BaseImport.php` — abstract; `validate()` + `import()` wrapped in single DB transaction; `err()`, `str()`, `req()` helpers

**Six import handlers:**
| Class | Import type |
|---|---|
| `DepartmentVwImport` | Departments and virtual warehouses |
| `PartnerImport` | Partners (type stored as JSON array on partners table) |
| `ItemCategoryImport` | Item categories with parent-before-child ordering |
| `ItemImport` | Items (validates `track_serial` requires count-type UoM) |
| `LocationImport` | Locations — auto-creates all parent segments from hierarchical codes |
| `OpeningStockImport` | Opening stock — overrides `import()` for chunked 500-line posting with idempotency keys; reads serials from sheet 2 of Excel; validates serial counts before writing |

**Two queue jobs:**
- `ValidateImportJob` — `ShouldBeUnique`; sets status to `validating`, calls `handler->validate()`
- `ConfirmImportJob` — `ShouldBeUnique`; re-validates before writing (amendment 8); calls `handler->import()`; if re-validation finds errors sets `failed_validation`

**Queue worker command:**
```bash
php artisan queue:work --queue=default --tries=1 --timeout=600
```

### Item guard additions (`app/Actions/Foundation/UpdateItemAction.php`)
- `track_serial = true` on non-count UoM → `DomainException` "count-type base unit"
- `track_serial` added to the "cannot change once stock exists" lock guard

### Stock verify additions (`app/Console/Commands/VerifyStockCommand.php`)
- For serial-tracked items, counts `in_stock` serials at the full 6-column stock key and compares to `qty_on_hand`; reports `serial_mismatch` separately

### New screens
- `/inventory/serials` — Serial Lookup: search by serial/item, filter by status + VW, slide-in history panel
- `/admin/imports` — Data Import index: upload form per type, permission checks
- `/admin/imports/{import}` — Import detail: status, error table, download error report, confirm button, `wire:poll.3s` auto-refresh
- Item detail: **Serials tab** added (only for serial-tracked items) — computed property wired up in `app/Livewire/Admin/Items/Show.php`

### Permissions added
`serials.view` (all roles), `imports.manage` (system-admin + warehouse-manager; warehouse-manager cannot upload Departments or Opening Stock)

### Key architectural decisions
- **Single-writer preserved**: `OpeningStockImport` calls `StockLedger::openingBalance()` — no direct stock table writes from import layer; arch test still passes
- **`$releasedInThisPost` pattern**: solves paired-line serial moves (relocation, hold/release) where the same serial appears on the outbound and inbound side of one `post()` call
- **`first_received_at` upsert**: DB upsert excludes `first_received_at` from the update columns so the original receipt timestamp is never overwritten on position changes
- **Chunked opening stock**: 500 lines/chunk, idempotency key `import:{id}:chunk:{n}` — safe to retry without double-posting
- **Re-validate on confirm**: `ConfirmImportJob` calls `validate()` again before writing to catch any data drift between upload and confirmation

### Tests
262 passing, 0 failures (up from 221 in Module 2). 28 new tests: 15 serial tracking (`tests/Feature/Inventory/SerialTrackingTest.php`), 13 import (`tests/Feature/Import/ImportTest.php`).

### Manual test checklist
`docs/testing/module-02b.md`

---

## What's next (Module 3+)

Build order follows `docs/WMS_Functional_Breakdown.md`:

- Module 3: Container Management — container registry, packing list import, yard/slot assignment, dwell time, auto-create GRN on unload
- Module 4: Receiving (GRN) + Put-Away — goods received note, inspection, put-away task list, scan-driven bin assignment
- Module 5: LPN / License Plate tracking
- Module 6: Picking & Allocation (FIFO/FEFO strategy)
- Module 7: Dispatch & Gate Pass
- Module 8: Stock Adjustments & Cycle Counts
- Module 9: Transfers
- Module 10: Reports & Export

---

## Source HTML template

`resources/whs-claude.html` — original design reference. Do not delete.
