# Dashboard Step 2: Charts with ApexCharts

Read PROJECT-BRIEF.md and docs/BUILD-CONVENTIONS.md first. Step 1 (KPIs) is done.
Step 1: short plan, wait for my confirmation.

## Setup
- npm install apexcharts (pin the version). Import in resources/js/app.js.
- Reusable Blade component x-ui.chart (type, series, categories, height) using Alpine:
    - Renders with ApexCharts, destroys and re-renders on Livewire updates (no duplicate charts after filter change or wire:navigate).
    - Reads colours from CSS tokens: brand #a22631 as main series, neutral grey as second series, semantic success/warning/danger for status. No blue/indigo.
    - Follows dark mode (axis, grid, tooltip, text colours) and switches live when the theme changes.
    - Inter font, no toolbar, no animations on refresh, responsive.
    - Empty state when there is no data.
- Data comes from DashboardMetrics (cached), passed as arrays. No queries in Blade or JS.

## Charts
1. Inbound vs outbound, last 7/30/90 days: line, two series (received qty vs dispatched qty per day)
2. Stock by department (VW): horizontal bar
3. Stock by category: horizontal bar (top 10 + "Other")
4. Order status pipeline: stacked bar (Submitted, Approved, Picking, Dispatched) per order type
5. Bin utilisation: radialBar, % of active bins with stock
6. Expiry profile: bar (Expired, 0-30, 31-90, >90 days)
7. Top 10 moving items: horizontal bar (dispatched qty in period)
8. Container dwell: bar per container in yard, colour by warning (7) / critical (14) days

## Role visibility
- manager / admin / data-analyst / auditor: all charts
- department-user: 2 (own VW only), 3, 6, 7 for their stock
- storekeeper / warehouse-officer: 4, 5, 8
- category users: 3, 6, 7 for their categories
  Use config/dashboard.php from Step 1.

## Layout
KPI cards on top, charts in a 2-column grid (1 column on mobile), action lists at the bottom. Glass cards, same spacing as the rest of the app.

## Tests (filter Dashboard)
Each chart data method returns correct series for seeded data, role visibility, scope respected, guard test still passes (no off-brand colours, no raw tables).
Short report.
