# Dashboard Step 1: KPI cards and action lists

Read PROJECT-BRIEF.md and docs/BUILD-CONVENTIONS.md first. Keep the current dashboard layout and glass design.
Step 1: short plan, wait for my confirmation.

## Service
- App\Services\Dashboard\DashboardMetrics: one method per metric. Reads only through StockQueryService and existing models (VW and category scope apply automatically).
- Cache each metric 5 minutes per user + filters (Cache::remember). Clear the cache on stock posting and order status changes.

## Filters (top of dashboard)
Warehouse, department (VW), date range 7 / 30 / 90 days. Stored in the query string.

## KPI cards
- Stock on hand (qty + SKU count)
- Open orders (PO / MRN / Transfers not completed)
- Pending approvals for me
- To receive today
- Awaiting put-away
- To pick / dispatch
- In transit
- Low stock (below min_stock)
- Expiring in 30 days or less
- Containers in yard (+ count over dwell warning)
  Each card links to the filtered list page.

## Action lists (x-ui.table)
- My pending approvals: number, type, department, age, Approve button
- Overdue orders: past expected/required date
- Recent variances: GRN and cycle count variances
- Recent activity: last 10 stock movements

## Role visibility
- department-user: stock on hand, open orders, low stock, expiring, their orders
- storekeeper / warehouse-officer: to receive, awaiting put-away, to pick/dispatch, transfers to execute
- category users: pending approvals + their category stock
- warehouse-manager / system-admin: everything
- data-analyst / hse-officer / viewer-auditor: KPIs read only, no action buttons
  Define visibility in one place (config/dashboard.php), not in Blade conditions.

## Tests (filter Dashboard)
Each metric returns correct numbers for seeded data, role visibility, VW/category scope respected, cache cleared after stock posting.
Short report.
