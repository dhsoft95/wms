---
name: WMS Project State
description: Current completion state of the WMS project — what's done, what's pending
type: project
---

Full back-end built through Module 12. All modules use real DB (Eloquent models, StockLedger, approval chains, Livewire data). UI shell is glassmorphism throughout.

**Why:** Ongoing build-out of a full WMS product on Laravel 11 + Livewire 3 + Tailwind v4.

**How to apply:** Next work should continue outbound/dispatch or any remaining modules from WMS_Functional_Breakdown.md.

## Modules Complete

- **Shell / Auth** — glassmorphism app shell, 22 Livewire pages, 12 Form Objects, design system, navigation, toasts, dark mode
- **Module 2** — Stock Ledger & Stock-on-Hand Engine (StockLedger, StockBalance, StockMovement, StockTransaction, reservations, verify command)
- **Module 2B** — Serial Number Tracking & Data Import (6 import types, SerialNumber, StockMovementSerial, queue jobs)
- **Module 3** — Container Management (Container, yard/slot, packing list import, dwell time, auto-create GRN)
- **Module 4** — Receiving (GRN) + Put-Away (GRN workflow, inspection, PutawayEngine, scan-driven bin assignment)
- **Module 5** — LPN / License Plate tracking
- **Module 6** — Outbound / Dispatch (DeliveryOrder, PickList, Dispatch, GatePass, ODN, POD)
- **Module 7** — Transfers (StockTransfer dept, WarehouseTransfer WH-to-WH)
- **Module 8** — Stock Adjustments & Cycle Counts (AdjustmentWorkflow, CycleCountWorkflow)
- **Module 9** — Purchase Orders (PO workflow, approval chain, GRN linkage)
- **Module 10** — Reports & Export (9 report types, PDF for 3, async queue export, AI summary, professional stepped UI)
  - Reports: Stock Holding, Stock Aging, Stock In Transit (as_of); Pending Orders, Delivered/Pending GRN, Planned Orders, Received & Dispatched (range); Stock Count, Asset Disposal (range + PDF)
  - All exports queued via GenerateReportJob → ReportReadyNotification; Excel via spatie/simple-excel lazy; PDF via dompdf landscape
  - ReportRegistry singleton; ReportDefinition contract; VW scope enforced per user role
- **Module 11** — Scanning (RF handheld emulator, BarcodeResolver, ScanContext, 8 scan modes)
- **Module 12** — Hazardous, Obsolete & Disposal (2026-10-22):
  - Hazardous: `is_hazardous`/`hazard_note`/`sds_file_path` on items; `LocationType::Hazardous` (pickable); PutawayEngine enforces item↔bin constraint
  - Obsolete: `StockStatus::Obsolete` (not pickable/allocatable); StockStatusRequest (SSR document, approval chain, StockLedger::changeStatus on approve); idempotent
  - Disposal: DisposalRequest (DISP document); full Draft→Submitted→Approved→Moved→Completed flow; qty lock checks on submit/approve; StockLedger::dispose() outbound; serials → Disposed; printable Disposal Note
  - MovementType: added `Obsolete` (paired), `Disposal` (outboundOnly)
  - SerialStatus: added `Disposed`
  - ApprovalChainDocumentType: added `Disposal`, `StockStatus`
  - Approvals inbox updated with disposal + SSR sections
  - Permissions: `disposals.create/approve/execute`, `obsolete.mark`
  - Tests: 15 passed, 1 skipped (HazardousItem full-stack requires complete GRN setup)

## Key architecture rules (applied throughout)
- All stock qty changes via `StockLedger` only
- Document numbers from `DocumentNumber::next()` only
- Quantities: `decimal(18,4)`, never float
- All multi-table writes in `DB::transaction()`
- Status enums with `canTransitionTo()` + `InvalidTransitionException`
- `BelongsToVirtualWarehouse` on all VW-scoped models
- `LogsActivity` on all master + document models
