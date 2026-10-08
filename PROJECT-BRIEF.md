# WMS — Project Brief

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
| Font | DM Sans (Google Fonts) |

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
`.icon-btn`, `.badge` + colour variants, `.dot`, `.table`, `.mono`, `.page-header`, `.page-title`,
`.page-sub`, `.sidebar-link` (+ `.active`), `.tabs`, `.tab` (+ `.active`), `.kpi-icon`,
`.dropdown-item`, `.list-row`, `.alert-info`, `.alert-warn`, `.radio-card`, `.dropzone`, `.progress`,
`.section-label`

---

## Layout shell

`resources/views/components/layouts/app/sidebar.blade.php`

- Replaces Flux's default sidebar entirely (auth pages are untouched)
- `<html x-data="{ sidebarOpen, darkMode }" :class="{ 'dark': darkMode }">` — dark mode persisted in `localStorage`
- Navigation driven by a PHP `$nav` array of groups → items → route names
- Active state via `request()->routeIs($route)`
- `<livewire:toast />` injected at the bottom of every page

### Toast system

- `app/Livewire/Toast.php` + `resources/views/livewire/toast.blade.php`
- Trigger from any Livewire component: `$this->dispatch('toast', message: '...', type: 'success|error|info')`

### Icon system

`resources/views/components/ui/icon.blade.php` — `<x-ui.icon name="..." class="..." />`
35 SVG icons stored as a PHP paths array. Supported names:
`archive`, `arrows`, `badge-check`, `bell`, `chart-bar`, `check`, `chevron-down`, `clipboard`,
`clock`, `cog`, `download`, `flag`, `inbox`, `list`, `logout`, `menu`, `moon`, `package`,
`plus`, `printer`, `search`, `shield`, `sun`, `trash`, `truck`, `upload`, `user`, `users`,
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

## What's next (back-end work)

1. Create Eloquent models: `Container`, `GoodsReceivedNote`, `GrnLine`, `Inspection`, `StockMovement`, `InventoryAdjustment`, `SalesOrder`, `OrderLine`, `GatePass`, `ProofOfDelivery`, `Sku`, `User`
2. Wire form `save()` methods to real model creates/updates
3. Replace placeholder arrays in index components with paginated Eloquent queries
4. Add proper `#[Validate]` rules (unique checks, FK existence, etc.)
5. File upload for POD document and GRN attachments (Livewire `WithFileUploads`)
6. Roles & permissions (e.g. Spatie)
7. PDF generation for gate pass / GRN print

---

## Source HTML template

`resources/whs-claude.html` — original design reference. Do not delete.
