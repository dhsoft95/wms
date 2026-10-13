# Module 4: Receiving (GRN) and Putaway

## Context
- Laravel + Livewire WMS. Done: Module 1 (Foundation), 2 (Stock Ledger), 2B (Serials + Data Import), 3 (Containers).
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` section 2 (Inbound: Receiving and Putaway), plus section 1 (container rules) and 5.1 (ledger).
- All stock is written only through `StockLedger` (`receive()`, `move()`), with serials, batches and LPNs. No direct writes to stock tables.
- Replace the Receiving (GRN) and Put-Away placeholder pages with real data. Use existing UI components, brand and semantic tokens only, modals, row-action menus, glass styles, light and dark mode. Guard tests (no raw tables, no off-brand colours) must keep passing.
- Full mobile scanner screens are Module 5. This module's receiving and putaway screens must still work with a keyboard-wedge barcode scanner (scan into a focused input field).
- Real label templates and thermal printing are Module 5. This module only generates LPN numbers and a simple printable A4 LPN sheet.

## Step 1 - Inspect and plan first
Report the existing GRN and Put-Away placeholder pages and routes, then propose tables, enums, services, the GRN status workflow, screens and permissions. Wait for my confirmation.

## Step 2 - Settings additions (Settings → Operations)
- Default **receiving dock** and **quarantine location** per warehouse.
- **Putaway strategy**: `consolidate` (same item's existing bin first), `zone` (empty bin in item's preferred zone), `manual`.
- Variance approval thresholds already exist (% and qty); use them.
- Add optional `preferred_zone_id` to items (Item form, Tracking or Stock Levels tab).

## Step 3 - Tables
- `grns`: `number` (GRN sequence), `source` (enum: `container`, `supplier`, `return`), `container_id` (nullable), `supplier_partner_id` (nullable), `warehouse_id`, `receiving_dock_location_id`, `status`, `received_by`, `confirmed_by`, `confirmed_at`, `closed_at`, `notes`, timestamps.
- `grn_lines`: `grn_id`, `container_expected_line_id` (nullable), `item_id`, `virtual_warehouse_id`, `uom_id`, `expected_qty` (nullable for manual/unexpected lines), `received_good_qty`, `received_damaged_qty` (both derived from receipts, base UoM), `variance_qty`, `variance_type` (enum: `none`, `short`, `excess`, `unexpected`), `variance_reason_code_id`, `remarks`, `is_unexpected` (bool).
- `grn_receipts` (each count/scan entry, so one line can be received in several pallets): `grn_line_id`, `qty`, `uom_id`, `batch_no`, `expiry_date`, `condition` (enum: `good`, `damaged`), `lpn_id` (nullable), `serials` via pivot `grn_receipt_serials`, `photos` (private files), `received_by`, `device`, `created_at`.
- `grn_variance_approvals`: `grn_id`, `requested_by`, `decided_by`, `decision` (`approved`, `rejected`), `notes`, timestamps.
- `putaway_tasks`: `grn_id`, `lpn_id` (nullable), `item_id`, `batch_id`, `virtual_warehouse_id`, `qty` (base), `from_location_id` (dock), `suggested_location_id`, `actual_location_id`, `status` (enum: `open`, `in_progress`, `done`, `cancelled`), `assigned_to`, `completed_by`, `completed_at`, `stock_transaction_id`.
- Extend `lpns`: `warehouse_id`, `current_location_id`, `grn_id`, `printed_at`, `print_count`. An LPN may hold more than one item (mixed pallet).

## Step 4 - GRN status workflow
Backed enum `GrnStatus` with `canTransitionTo()`; all transitions through `GrnWorkflow`, transactional, with history in the activity log.

`draft` → `receiving` → (`pending_variance_approval`) → `received` → `putaway_in_progress` → `putaway_complete` → `closed`. Also `draft`/`receiving` → `cancelled` (only if nothing confirmed).

### Creating GRNs
- **From a container (automatic):** listen to `ContainerUnloadingStarted`; create one GRN (source `container`) with one line per expected line (item, VW, expected qty in base UoM, batch/expiry from packing list as defaults). Dock = warehouse default dock. Idempotent: the event firing twice must not create two GRNs.
- **Manual (supplier delivery or return):** create draft with source, supplier (or customer for returns), warehouse, dock, and lines added manually or by scanning.

### Receiving (status `receiving`)
- Receiving screen: big scan/search input at the top (item barcode, SKU, or LPN for re-scan). A scan matches an item to its expected line; if the item is not on the GRN, it creates an **unexpected** line (needs VW selection).
- For each entry: qty + UoM, batch and expiry (required if the item tracks them; pre-filled from the packing list, editable), condition (good/damaged), optional photos, serials for serial items (scan one by one, live count, duplicates and already-in-stock rejected immediately), and optional "Assign to pallet": new LPN or existing open LPN on this GRN.
- Entries are saved as `grn_receipts` and can be edited or deleted until the GRN is confirmed.
- Live line progress: expected vs received good / damaged, variance badge.

### Variance and confirmation
- On "Finish receiving", the system calculates per line: **short** (received < expected), **excess** (received > expected), **unexpected** (not on the packing list).
- Every variance line needs a reason code (`applies_to = variance`).
- If any line exceeds the configured threshold (% or qty), the GRN goes to `pending_variance_approval` and a Warehouse Manager must approve or reject (reject sends it back to `receiving`). Notify managers; show the request in the existing **Approvals Inbox**.
- **Confirm** (`received`), in one DB transaction:
    - Post stock through `StockLedger::receive()` in one STK transaction with idempotency key `grn:{id}:confirm`:
        - Good qty → receiving dock, status `available`, per batch, LPN and VW, with serials.
        - Damaged qty → warehouse quarantine location, status `on_hold`, with serials.
    - Container GRN: write variances to `container_discrepancies` per expected line (short, excess, damaged, unexpected) with reason codes and photos.
    - Create putaway tasks: one per LPN; for receipts without LPN, one per line/batch/VW. Damaged/quarantine stock gets no putaway task.
    - Confirmed GRNs become read-only. Corrections are done later through stock adjustments (Module 7), never by editing the GRN.
- Notify users of each VW that received goods ("Goods received for your department: GRN-2026-0012").

### Container link
Replace `DefaultContainerReceivingGate` with a real implementation: a container can be marked `unloaded` only when all its GRNs are `received` or later. Automatically move the container to `unloaded` when its GRN is confirmed. Container detail "Received & Variance" tab shows expected vs received per line, variance, condition and GRN links (replace the placeholder).

## Step 5 - Putaway
- **Suggestion** per task using the configured strategy:
    - `consolidate`: bin that already holds the same item (any VW), active, pickable storage, with free capacity; else fall back to `zone`.
    - `zone`: empty active storage bin in the item's preferred zone; else any empty storage bin.
    - `manual`: no suggestion.
- **Capacity check:** `max_pallets` (count of LPNs currently in the bin) and `max_weight_kg` (sum of item weight × qty). Set location status to `full` automatically when pallet capacity is reached and back to `active` when it drops below. Manual `blocked` is never overwritten.
- **Execution:** open a task, scan or select the destination bin (suggested one pre-filled, can be changed). Validate: location active (not blocked or full), storage type and pickable, enough capacity. Then `StockLedger::move()` from dock to bin for the whole LPN contents (or the task qty for non-LPN tasks, partial allowed by splitting the task). Stock becomes **available for picking** only after this move (dock is not pickable).
- Task list: filters by status, GRN, assignee, zone; bulk assign to a storekeeper; "My tasks" view.
- GRN status moves to `putaway_in_progress` on the first completed task and `putaway_complete` when all are done. A Warehouse Manager closes the GRN (`closed`).

## Step 6 - Screens
1. **GRN list** (replace placeholder): status tabs with counts; filters (source, container, supplier, VW, date range); columns: GRN no. (mono), source, container/supplier, dock, lines, expected vs received, variance badge, status, received by, date, row actions.
2. **New manual GRN** page.
3. **GRN detail**: header with status stepper and actions; tabs: Lines (expected, good, damaged, variance, reason), Receipts (each entry with batch, expiry, LPN, serials, photos, user), LPNs, Putaway tasks, Variance approval, Activity.
4. **Receiving screen** (scan-driven, works on desktop and tablet): described in Step 4.
5. **Variance review** modal/page for managers (approve/reject with notes), also reachable from Approvals Inbox.
6. **Put-Away tasks** page (replace placeholder) + **putaway execution** modal.
7. **Printable LPN sheet** (A4 PDF): LPN code as Code128 barcode, GRN no., item(s), qty, batch, VW, date. Reprint logged in `print_count`.
8. **Dashboard**: real "Pending GRNs" stat (receiving + pending approval), "Today's receipts", "Pending putaway" count, and recent activity entries for GRN confirm and putaway.

## Step 7 - VW visibility
- Department users see GRNs that have at least one line in their VWs, and only their own lines inside (same pattern as containers, via a query service).
- They cannot receive, confirm or put away; view only.

## Step 8 - Permissions
`grn.view`, `grn.create`, `grn.receive`, `grn.confirm`, `grn.variance.approve`, `grn.cancel`, `grn.close`, `putaway.view`, `putaway.execute`, `putaway.assign`, `lpn.print`.

| Role | Access |
|---|---|
| System Admin | All |
| Warehouse Manager | All |
| Storekeeper | grn.view, grn.create, grn.receive, grn.confirm (only when no approval needed), putaway.view, putaway.execute, lpn.print |
| Department User | grn.view (own VW lines) |
| Viewer / Auditor | grn.view, putaway.view |
| Scanner Operator | none yet (Module 5 scanner screens) |

Register pages in `config/navigation.php` (Inbound: Containers, Receiving (GRN), Put-Away). Print the matrix.

## Step 9 - Seeder
`ReceivingDemoSeeder`: GRNs in every status, including a container GRN with short, excess, damaged and unexpected lines, one waiting for variance approval, LPNs, and open/done putaway tasks. All stock posted through the ledger.

## Step 10 - Tests (Pest)
- Container unloading creates exactly one GRN (idempotent event).
- Receipt entries: batch/expiry rules, serial rules (count, duplicate, already in stock), unexpected item line, edit/delete before confirm only.
- Variance calculation (short, excess, unexpected), reason code required, threshold triggers approval, reject returns to receiving.
- Confirm posts correct ledger lines: good to dock (available), damaged to quarantine (on hold), with batches, LPNs, VWs, serials; idempotent confirm (second call posts nothing).
- Container discrepancies written; container auto-moves to `unloaded`; real gate blocks unloaded until GRN confirmed.
- Confirmed GRN is read-only.
- Putaway suggestion for each strategy; capacity check (pallets and weight); auto `full` and back to `active`; blocked bin rejected; quarantine/dock/yard rejected as destination.
- Putaway moves whole LPN; partial split for non-LPN tasks; stock becomes pickable only after putaway.
- GRN status progression to `putaway_complete` and `closed`.
- VW visibility and 404 for other departments.
- Permissions per role (Livewire tests).
- `wms:stock:verify` clean after the full receive + putaway flow (including serials).
- `mysql` group: two users confirming the same GRN at the same time posts stock once; two putaways into a bin with 1 free pallet slot, only one succeeds.

## Step 11 - Manual test checklist
Write `docs/testing/module-04.md`: container → unloading → GRN → receive with scanner (good, damaged, unexpected, serials, batches) → variance approval → confirm → LPN sheet → putaway (suggested and manual bin, full bin) → close; check Stock on Hand, Movement History, Serial Lookup and container variance tab at each step.

## Deliverable
Tables, enums, workflow, services, ledger usage, screens, permission matrix, files changed, test results (`php artisan test` 0 failures, `mysql` group, `npm run build`), decisions taken.

Stop when done. Do not start Module 5.
