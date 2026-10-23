# Reports Module (lean)

Do NOT scan the whole project. Read only: docs/BUILD-CONVENTIONS.md, StockQueryService, ReportSummaryService, x-ui.table, config/navigation.php.
Read-only reports. VW/category scope applies. No new stock logic.

## UI (one page: Reports)
Form: Report dropdown | Date range or "As of date" (auto by report) | Warehouse | Department | Generate.
Below: AI summary card, paginated table, Export Excel, Export PDF (only where enabled).

## Registry
ReportDefinition interface: key, label, periodType (range|as_of), columns(), query(filters, user), pdfEnabled.
List reports in config/reports.php. Dropdown, export and AI summary read from it.

## Reports
As of: 1 Stock Holding, 2 Stock Aging (0-30/31-90/91-180/180+ from received_at), 3 Stock in Transit
Range: 4 Pending Orders (PO/MRN/STN, age), 5 Delivered & Pending GRN, 6 Planned Orders (approved POs not arrived),
7 Received & Dispatched (filters: doc type PO/MRN/STN/All, direction, group by day/week/month; count + qty),
8 Stock Count (variance, accuracy %), 9 Asset Disposal

## Export
Excel all (spatie/simple-excel). PDF only 4, 8, 9 (dompdf, company header, signature lines on 8 and 9).
> 5,000 rows: queued, notify with link.

## Permissions
reports.view / reports.export: managers, supervisor, admin, data-analyst, auditor, hse, department-user (own scope).

## Rules for you
- Stage 1: registry, page, reports 1-4, Excel. Stop and give a 5-line report.
- Stage 2 (when I say go): reports 5-9, PDF, queued export.
- Tests: one test per report + one scope test. Run only --filter=Reports.
- Report: files changed + pass/fail count only. No code dumps.
