# Task: Implement the glassmorphism auth design for all auth pages

## Context
- Reference design: `resources/design/auth-login.png`. Open it and match it as closely as possible.
- Background photo: `public/images/auth/warehouse-bg.jpg`. If missing, stop and ask me.
- Laravel + Livewire starter kit (Flux/Volt as installed). UI shell and `resources/views/components/ui/*` are already built from `resources/whs-claude.html`.
- Read `PROJECT-BRIEF.md` first and update it when done.
- UI only. Do not change auth logic, routes, Fortify config, validation or redirects.
- The system is reusable across clients. All branding text comes from config, not Blade. No hardcoded client names, logos or copy in views.

## Design style: Glassmorphism (mandatory)
This is a glassmorphism design. Every surface must look like frosted glass over the blurred warehouse background, not flat white or flat brand color.

**Glass rules applied everywhere**
- Translucent fills only: white or brand at 8% to 70% opacity, never solid.
- `backdrop-blur` on every glass surface (`backdrop-blur-xl` to `backdrop-blur-2xl`), with `backdrop-saturate-150`.
- Thin light borders: `border-white/40` to `border-white/60` (dark: `border-white/10`).
- Inner highlight: top edge light line via inset shadow.
- Soft outer shadow for depth.
- Subtle diagonal sheen on the card: a `before:` pseudo-element with a white gradient (white/40 to transparent at ~45deg).
- Rounded corners: 3xl for the card, xl for inputs, tiles and buttons.
- The background must stay visible and blurred through the glass. Never cover it with an opaque layer.

## Step 1 - Inspect and report first
1. Open `resources/design/auth-login.png` and `resources/whs-claude.html`.
2. Extract the existing design tokens: brand color and its hover/active shades, neutrals, font family and weights, radius, focus ring style, dark mode handling.
3. List every auth-related view currently in the project, for example:
   - login
   - register (confirm whether it is still enabled; users are invite-only)
   - forgot password
   - reset password
   - verify email
   - confirm password
   - two-factor challenge (authenticator code, email OTP, recovery code)
   - accept invitation / set password
   - auth layout(s) (`components/layouts/auth*`)
4. Report the token list and the file list. Wait for my confirmation before editing.

## Step 2 - Brand tokens and glass utilities
In `resources/css/app.css`:

- Define all tokens once in the Tailwind `@theme` block, named semantically: `--color-brand`, `--color-brand-hover`, `--color-brand-soft`, `--color-surface`, `--color-border`, `--color-muted`. Use the brand values extracted in Step 1 (deep maroon from the reference design).
- Map Flux accent variables (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`) to the brand tokens so Flux buttons, links, checkboxes and focus rings follow the brand.
- Set the template font as the default `--font-sans`, loaded through Vite or the existing font setup, not a new CDN link.
- Replace hardcoded hex values in auth-related views with these tokens.

Add these utilities:

```css
@utility glass {
  background-color: rgb(255 255 255 / 0.65);
  backdrop-filter: blur(24px) saturate(150%);
  -webkit-backdrop-filter: blur(24px) saturate(150%);
  border: 1px solid rgb(255 255 255 / 0.55);
  box-shadow: inset 0 1px 0 0 rgb(255 255 255 / 0.6), 0 8px 32px 0 rgb(0 0 0 / 0.18);
}

@utility glass-dark {
  background-color: rgb(24 24 27 / 0.55);
  backdrop-filter: blur(24px) saturate(150%);
  -webkit-backdrop-filter: blur(24px) saturate(150%);
  border: 1px solid rgb(255 255 255 / 0.1);
  box-shadow: inset 0 1px 0 0 rgb(255 255 255 / 0.08), 0 8px 32px 0 rgb(0 0 0 / 0.35);
}

@utility glass-brand {
  background-color: color-mix(in oklab, var(--color-brand) 25%, transparent);
  backdrop-filter: blur(16px) saturate(150%);
  -webkit-backdrop-filter: blur(16px) saturate(150%);
  border: 1px solid color-mix(in oklab, var(--color-brand) 55%, white 15%);
  box-shadow: inset 0 1px 0 0 rgb(255 255 255 / 0.15), 0 4px 20px 0 color-mix(in oklab, var(--color-brand) 35%, transparent);
}

@utility glass-input {
  background-color: rgb(255 255 255 / 0.55);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgb(255 255 255 / 0.7);
  box-shadow: inset 0 1px 2px 0 rgb(0 0 0 / 0.04);
}
```

Add a fallback for browsers without `backdrop-filter`:

```css
@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
  .glass { background-color: rgb(255 255 255 / 0.92); }
  .glass-dark { background-color: rgb(24 24 27 / 0.92); }
  .glass-input { background-color: rgb(255 255 255 / 0.9); }
}
```

**Where each utility applies**

| Element | Style |
|---|---|
| Form card | `glass` (dark: `glass-dark`) + diagonal sheen + `rounded-3xl` |
| Brand initials tile | `glass-brand` with brand gradient tint |
| Feature icon tiles (left panel) | `glass-brand` |
| Inputs | `glass-input`, focus: `ring-2 ring-brand/40 border-brand/50` |
| Secure access note | `glass-input`, lighter |
| Two-factor method toggle | `glass-input` track with `glass` active pill |
| Alerts | `glass` with a colored left border |
| Primary button | Brand gradient (not glass) with brand/40 glow and white/20 inner top highlight, so it stands out against the glass |

## Step 3 - Branding config
Create or extend `config/wms.php`:

```php
'branding' => [
    'name' => env('WMS_BRAND_NAME', config('app.name')),
    'subtitle' => env('WMS_BRAND_SUBTITLE', 'Warehouse Management System'),
    'initials' => env('WMS_BRAND_INITIALS', 'SC'),
    'logo' => env('WMS_BRAND_LOGO'), // optional image; falls back to initials tile
    'auth_background' => env('WMS_AUTH_BACKGROUND', 'images/auth/warehouse-bg.jpg'),
    'headline' => 'Warehouse operations,',
    'headline_accent' => 'from yard to doorstep.',
    'tagline' => 'Smarter warehouse management for greater visibility, accuracy and efficiency, all in one powerful system.',
    'features' => [
        ['icon' => 'container', 'title' => 'Container Management', 'text' => 'Track arrivals, parking and container status in real time.'],
        ['icon' => 'arrows-right-left', 'title' => 'Inbound & Outbound', 'text' => 'Manage receiving, put-away, picking and dispatch.'],
        ['icon' => 'cube', 'title' => 'Stock Movements', 'text' => 'Relocations, transfers, adjustments and more.'],
        ['icon' => 'chart-bar', 'title' => 'Reporting & Visibility', 'text' => 'Accurate data, better decisions, full warehouse oversight.'],
    ],
    'footer_pillars' => ['Safe', 'Efficient', 'Connected'],
],
```

Add the matching keys to `.env.example`.

## Step 4 - Shared auth layout
Replace the current auth layout with one layout used by every auth page: `resources/views/components/layouts/auth.blade.php`. Remove the unused starter kit auth layout variants from use.

The layout accepts `title` and `description` props.

**Background layering (bottom to top)**
1. Warehouse photo from `config('wms.branding.auth_background')`, `bg-cover bg-center`, fixed full screen, slightly scaled (`scale-105`) to hide blur edges.
2. Left brand overlay gradient (brand/85 to brand/30, left to right).
3. Right light wash (white/10 to white/30) behind the card area.
4. Decorative diagonal brand shapes in the bottom-left and top/bottom-right corners, semi-transparent, some with `backdrop-blur-sm` so they read as glass panes. `pointer-events-none`, `aria-hidden`.
5. Content.

**Left panel (hidden below `lg`, ~55% width)**
- Brand block: rounded-2xl square `glass-brand` tile with brand gradient tint showing `initials` (or `logo` if set), next to `name` (large, white, semibold) and `subtitle` (white/70).
- Headline: very large bold white text, `headline` on line one, `headline_accent` in a lighter brand tint on line two.
- Tagline paragraph in white/80, max width ~28rem.
- 2x2 feature grid using `feature-tile`: `glass-brand` icon tile with a white outline icon, title (white, semibold), description (white/70, small).
- Footer: thin white/15 divider, shield-check icon in brand tint, `footer_pillars` uppercase, tracked wide, separated by `/`.

**Right panel (form side)**
- Glass card: `max-w-lg`, `rounded-3xl`, `glass` (dark: `glass-dark`), diagonal sheen, generous padding (`p-10 lg:p-14`), `relative overflow-hidden`.
- Short brand accent bar (h-1, w-12, rounded-full) above the title.
- `{{ $title }}` large bold dark heading, `{{ $description }}` muted paragraph.
- `{{ $slot }}` for the form.

**Mobile (below `lg`)**
- Hide the left panel; show the brand block above the card.
- Card becomes full width with smaller padding (`p-6`).
- Form must be fully usable at 360px width.

## Step 5 - Auth form components (glassmorphism)
Create in `resources/views/components/auth/`:
- `input`: label above, `glass-input`, rounded-xl, height ~3.5rem, leading icon slot (mail, lock, user, key) in muted color, brand focus ring, `@error` message below, accepts `wire:model` via `$attributes`.
- `password`: same as `input` with lock icon and show/hide eye toggle (Alpine).
- `checkbox`: glass unchecked state, brand-filled when checked.
- `button`: full width, rounded-xl, height ~3.5rem, brand gradient, white semibold text, trailing arrow icon, brand glow shadow, inner top highlight, hover lift, `wire:loading` spinner and disabled state.
- `divider`: white/50 lines with centered muted `or`.
- `secure-note`: `glass-input` box with shield icon and "Secure access to {{ brand name }}".
- `link`: brand-colored small semibold link.
- `alert`: `glass` surface with colored left border for status, error and throttle messages.
- `method-toggle`: segmented control, `glass-input` track with `glass` active pill.
- `feature-tile`: `glass-brand` icon tile with title and description for the left panel.

## Step 6 - Pages
Every page uses the shared layout and the components above. Match the login page exactly to the reference; the others follow the same structure.

| Page | Title | Description | Fields / actions |
|---|---|---|---|
| Login | Sign in to your account | Enter your email and password below to continue to {name}. | email, password with "Forgot password?" link right of the label, remember me, Sign in, divider, secure note |
| Forgot password | Forgot your password? | Enter your email and we will send you a reset link. | email, Send reset link, back to sign in |
| Reset password | Set a new password | Choose a strong password for your account. | email (read-only), password, confirm password, Reset password |
| Accept invitation | Activate your account | Set a password to finish setting up your account. | name and email shown read-only, password, confirm password, Activate account |
| Verify email | Verify your email | Check your inbox for the verification link. | Resend link, log out link |
| Confirm password | Confirm your password | This is a secure area. Confirm your password to continue. | password, Confirm |
| Two-factor challenge | Two-factor verification | Enter the code to continue. | `method-toggle`: Authenticator / Email code / Recovery code; code input; email code has Resend with cooldown; Verify |

- `{name}` comes from `config('wms.branding.name')`.
- Remove the register page from the UI if self-registration is disabled; keep the route behaviour as it is now.
- Remove all starter kit branding and copy (Laravel logo, starter kit text).
- Status, validation and throttle messages use the `alert` component.

## Step 7 - Emails
Restyle the notification mail layout (password reset, invitation, email OTP, verify email) with the brand color, logo or initials, and font fallback. Glass effects are not supported in email clients, so use solid brand header and clean white body. Publish the mail views only if needed.

## Step 8 - Details
- Fonts and colors only from tokens.
- Dark mode: card uses `glass-dark`, text inverts, inputs use a dark translucent variant, background overlay stays.
- Accessibility: labels tied to inputs, visible focus states, icons `aria-hidden`, text contrast on glass meets WCAG AA (raise glass opacity if needed).
- Background image compressed; if rendered as `<img>`, use `loading="eager"` and `fetchpriority="high"`.
- Respect `prefers-reduced-motion` for hover lift and transitions.

## Step 9 - Verify
- Visit every auth route logged out at 1440px, 1024px and 375px widths.
- Compare login against `resources/design/auth-login.png` and fix spacing, sizes, blur and colors until it matches.
- Confirm the background is visible through every glass surface.
- Test in Chrome, Firefox and Safari (`-webkit-backdrop-filter`).
- Run Pest auth tests; all must pass.
- `npm run build` with no warnings.

## Deliverable
- Final token list and glass utilities.
- Files changed.
- Notes on any deviation from the reference design and why.
