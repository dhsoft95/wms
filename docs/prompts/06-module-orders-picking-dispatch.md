# Module 6: Delivery Orders, Picking and Dispatch

## Context
- Laravel + Livewire WMS. Done: Modules 1, 2, 2B, 3, 4, 5.
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` section 3 (Outbound: Picking and Dispatch), plus 4.1 (available = on hand − allocated − on hold), 6 (VW rules) and 8 (scan workflows Pick and Dispatch).
- All stock changes go only through `StockLedger` and `StockReservations` (reserve/release; consumption happens inside the pick posting). No direct writes to stock tables.
- Scanner Pick and Dispatch use the Module 5 framework (`BarcodeResolver`, GS1, feedback, `request_id` idempotency, device code on ledger).
- Replace the Orders, Dispatch & Gate, and Proof of Delivery placeholder pages with real data. Use existing UI components, brand and semantic tokens only, modals, row-action menus, light and dark mode. Guard tests must keep passing.

## Step 1 - Inspect and plan first
Report existing outbound placeholder pages, routes, permissions and scanner Pick stub. Propose tables, enums, status workflow, allocation algorithm, services, screens and permissions. Wait for my confirmation.

## Step 2 - Settings (Settings → Operations, new "Outbound" section)
- **Approval mode**: `none`, `department_head`, `warehouse_manager`, `both` (two levels: department head, then warehouse manager).
- **Allow partial allocation** (yes/no) and **auto backorder** for the remainder (yes/no).
- **FEFO minimum remaining shelf life** (days); stock expiring sooner is not allocated.
- **Short pick behaviour**: `reallocate` (try other bins automatically) or `leave_short`.
- Per warehouse (column on `warehouses`): `default_staging_location_id` (must be type `staging`).
- Optional `pick_sequence` (int) on locations to override the default walking route.

## Step 3 - Tables
- `delivery_orders`: `number` (DO sequence), `virtual_warehouse_id` (one VW per DO), `department_id`, `consignee_partner_id`, `delivery_address`, `required_date`, `priority` (`low`, `normal`, `high`, `urgent`), `allocation_rule` (`default`, `fifo`, `fefo`), `status`, `requested_by`, `submitted_at`, `remarks`, `backorder_of_id` (nullable self FK), `warehouse_id`, timestamps, soft deletes (draft only).
- `delivery_order_lines`: `delivery_order_id`, `line_no`, `item_id`, `uom_id`, `requested_qty`, `requested_qty_base`, `requested_batch_id` (nullable), `allocated_qty`, `picked_qty`, `short_qty`, `dispatched_qty`, `status`, `notes`.
- `delivery_order_approvals`: `delivery_order_id`, `level` (1 or 2), `approver_role` (`department_head`, `warehouse_manager`), `decided_by`, `decision` (`approved`, `rejected`), `notes`, `decided_at`.
- `delivery_order_allocations`: `delivery_order_line_id`, `stock_balance_id`, `stock_reservation_id`, `item_id`, `batch_id`, `lpn_id`, `location_id`, `qty`, `status` (`reserved`, `picked`, `released`).
- `pick_lists`: `number` (new sequence `PCK`), `delivery_order_id`, `status` (`open`, `in_progress`, `completed`, `cancelled`), `assigned_to`, `started_at`, `completed_at`.
- `pick_tasks`: `pick_list_id`, `delivery_order_allocation_id`, `sequence`, `location_id`, `item_id`, `batch_id`, `lpn_id`, `qty_to_pick`, `qty_picked`, `qty_short`, `status` (`open`, `picked`, `short`, `cancelled`), `picked_by`, `picked_at`, `stock_transaction_id`, `short_reason_code_id`.
- `pick_task_serials` (pivot to `serial_numbers`) for serial items.
- `stock_count_requests` (queue for Module 7): `location_id`, `item_id`, `reason` (`short_pick`, `other`), `reference_type`, `reference_id`, `requested_by`, `status` (`open`, `converted`, `closed`), timestamps.
- `dispatches`: `number` (new sequence `DSP`), `delivery_order_id`, `vehicle_no`, `driver_name`, `driver_phone`, `driver_id_no` (nullable), `transporter_partner_id` (nullable), `seal_no` (nullable), `dispatched_at`, `dispatched_by`, `stock_transaction_id`, `notes`.
- `dispatch_items` (what was loaded): `dispatch_id`, `lpn_id` (nullable), `item_id`, `batch_id`, `qty`, scanned flag.
- `gate_passes`: `number` (new sequence `GP`), `dispatch_id`, `status` (`issued`, `exited`), `exited_at`, `verified_by`.
- `proof_of_deliveries`: `delivery_order_id`, `received_by_name`, `received_at`, `notes`, `signature_path` (nullable), `uploaded_by`, plus files (photos/scans) in a child table or JSON list, private storage.

## Step 4 - Status workflow
Backed enum `DeliveryOrderStatus` with `canTransitionTo()`; all transitions through `DeliveryOrderWorkflow`, transactional, history in the activity log.

`draft` → `submitted` → `approved` (→ `rejected` back to `draft` with reason) → `allocated` / `partially_allocated` → `picking` → `picked` → `dispatched` → `delivered`. Also `cancelled` from any state before `dispatched`.

Rules (from spec):
- A department user can only create DOs for their own VW.
- Approval follows the approval mode. With `none`, submit goes straight to `approved`.
- **Allocation reduces available qty immediately**, before picking, so two orders can't take the same stock.
- **Cancelling** an allocated or picking order releases all reservations; stock already picked to staging is moved back to its source bin (or a chosen bin) through the ledger.
- **Dispatched orders cannot be edited**; reversals go through a return GRN (Step 9).

## Step 5 - Allocation (`AllocationService`)
- Candidates: balances for the line's item in the DO's VW, `stock_status = available`, location type `storage` and pickable, location `active` (not blocked), not reserved beyond on hand. Never allocate from dock, staging, quarantine or yard.
- Strategy:
    - **Specific batch** if `requested_batch_id` is set.
    - **FEFO** for expiry-tracked items (or when the DO forces FEFO): earliest expiry first; skip batches expiring within the minimum shelf life.
    - **FIFO** otherwise: oldest first received (batch first receipt date, then balance creation).
    - Tie-break: fewer locations first (prefer bins that can fill the line in one pick), then pick sequence.
- Each chosen balance gets a reservation (`StockReservations::reserve`) with the balance row locked.
- **Insufficient stock**: if partial allocation is allowed, allocate what exists; status `partially_allocated`. If auto backorder is on, create a new DO (`backorder_of_id`) for the remainder in `approved` status and close the remainder on the original. Otherwise the user can retry allocation later or cancel the remainder.
- **Re-allocate** and **release allocation** actions for managers before picking starts.
- Serial items are allocated by quantity; the actual serials are chosen at pick time from the allocated stock key.

## Step 6 - Picking
- **Generate pick list** for an allocated DO: one pick task per allocation, sorted by walking route (location `pick_sequence` if set, otherwise natural order of zone → aisle → rack → level → bin codes).
- Assign to a picker (bulk assign); "My pick lists" view.
- **Pick execution** (desktop and scanner, same service `PickingService`):
    1. Scan bin → must be the task's bin.
    2. Scan item or LPN → must match the task's item, batch and LPN. Wrong item or wrong batch is rejected at scan (spec).
    3. Qty (keypad); serial items: scan each serial, which must be in stock at that exact stock key.
    4. Confirm → ledger posting type `pick`: move from bin to the warehouse staging location, consuming the reservation in the same posting (on hand and allocated reduced together at the bin), serials moved. Staged stock is linked to the DO through the task/allocation and is not available for other orders (staging is never an allocation source).
- **Short pick** (spec): picker enters the qty actually found and a reason code (`short_pick`). The missing qty's reservation is released; a `stock_count_requests` row is created for that bin and item, and Warehouse Managers are notified ("system stock is probably wrong"). If short pick behaviour is `reallocate`, try to allocate the missing qty from other bins and add new tasks to the same pick list.
- DO status: `picking` on first pick, `picked` when all tasks are picked or short.

## Step 7 - Dispatch
- Dispatch screen for a `picked` DO: what is in staging for this DO (items, batches, LPNs, serials).
- Loader enters vehicle no., driver name, driver phone, driver ID (optional), transporter (optional), seal no. (if containerised), notes.
- **Load check**: scan each LPN/item being loaded (scanner Dispatch workflow: scan DO → scan each LPN loaded). Everything staged for the DO must be scanned or explicitly marked "not loaded". Not-loaded stock is moved back to a bin (ledger `relocation`) and the line's dispatched qty reduced.
- **Confirm dispatch** (one transaction): ledger posting type `dispatch` removes the stock from staging (serials → `dispatched`), DO lines get `dispatched_qty`, DO → `dispatched`, dispatch record and **gate pass** created. Idempotency key `do:{id}:dispatch`.
- Print **Delivery Note / Goods Delivery Note** (A4 PDF): company header and logo, DO no., dispatch no., date, consignee and address, lines (SKU, description, batch, expiry, qty, UoM), serial numbers listed per line, vehicle, driver, seal, signature blocks (dispatched by / driver / received by). Also print **dispatch labels** using the Module 5 `dispatch` template.
- **Gate pass** (printed with the delivery note, QR code with the GP number): security scans or enters the GP number on a simple **Gate Check** screen (desktop or scanner) to confirm the vehicle exited; shows vehicle, driver and DO summary to compare. Status `issued` → `exited`.

## Step 8 - Proof of delivery
- On a `dispatched` DO: upload signed delivery note photos/scans, received-by name, date/time, notes; optional signature pad (touch) on the scanner device. DO → `delivered`.
- Proof of Delivery page: list of dispatched DOs awaiting POD (age in days), filters, upload modal.

## Step 9 - Returns
- "Create return" on a dispatched/delivered DO opens a manual GRN (Module 4, source `return`) linked to the DO, with lines pre-filled from dispatched items (batches and serials pre-listed). Receiving follows the normal Module 4 flow; returned serials go back to `in_stock`.
- Add `delivery_order_id` (nullable) to `grns` for this link.

## Step 10 - Screens
1. **Orders list** (replace placeholder): status tabs with counts; filters (VW/department, consignee, priority, required date range, overdue); columns: DO no. (mono), department/VW, consignee, required date (red if overdue), priority badge, lines, allocated %, status, requested by; row actions per workflow.
2. **New / Edit DO** (draft only): header + lines (searchable item, qty, UoM, optional batch); shows available qty per item for the DO's VW while typing.
3. **DO detail**: status stepper; tabs: Lines (requested, allocated, picked, short, dispatched), Allocation (which batch/location is reserved, spec screen), Pick lists, Dispatch, Proof of delivery, Approvals, Activity. Action buttons per status.
4. **Approvals Inbox**: add DO approvals (level 1 department head, level 2 manager) next to GRN variance approvals; approve/reject with notes.
5. **Pick lists page**: open/in progress/completed, assign, "My pick lists", desktop pick execution modal.
6. **Dispatch & Gate page** (replace placeholder): DOs ready to dispatch, dispatch screen, recent dispatches, Gate Check.
7. **Proof of Delivery page** (replace placeholder).
8. **Scanner**: enable **Pick** (choose pick list or scan pick list/DO no. → task by task in route order, with "next bin" shown large) and **Dispatch** (scan DO → scan each LPN loaded → confirm) tiles; **Gate check** for users with the permission.
9. **Dashboard**: real "Today's dispatches" with on-time rate (dispatched on or before required date), orders awaiting approval, open pick lists, overdue orders; recent activity entries for dispatch and gate exit.

## Step 11 - VW visibility
- Department users: create and see only their own VW's DOs; see allocation and dispatch details for their DOs; can upload POD if permitted; never execute picking or dispatch.
- Department head approval only for DOs of their department.

## Step 12 - Notifications
Submitted → approvers; approved/rejected → requester; partially allocated or backorder created → requester and managers; short pick → managers; dispatched → requester and VW users (with DN number); delivered → requester. Database + email, queued.

## Step 13 - Permissions
`orders.view`, `orders.create`, `orders.submit`, `orders.approve.department`, `orders.approve.warehouse`, `orders.allocate`, `orders.cancel`, `picking.view`, `picking.assign`, `picking.execute`, `dispatch.view`, `dispatch.execute`, `gate.verify`, `pod.upload`, `returns.create`, `scan.pick`, `scan.dispatch`, `scan.gate`.

| Role | Access |
|---|---|
| System Admin | All |
| Warehouse Manager | All |
| Department User | orders.view, orders.create, orders.submit (own VW); orders.approve.department only if they are the department head; pod.upload (optional, off by default) |
| Storekeeper | orders.view, orders.allocate, picking.*, dispatch.*, pod.upload, returns.create, scan.pick, scan.dispatch |
| Scanner Operator | scan.pick, scan.dispatch |
| Viewer / Auditor | orders.view, picking.view, dispatch.view |
| (Security guard, optional new role `gate-security`) | gate.verify, scan.gate only |

Register pages in `config/navigation.php` (Outbound: Orders, Pick Lists, Dispatch & Gate, Proof of Delivery). Print the matrix.

## Step 14 - Seeder
`OutboundDemoSeeder`: DOs in every status across VWs, including FEFO items with near-expiry batches, one partial allocation with backorder, one short pick with a count request, dispatched with gate pass exited, delivered with POD, one return. All stock through the ledger.

## Step 15 - Tests (Pest)
- Department user can only order from own VW; 404 on others.
- Approval modes (none, department head, manager, both); reject returns to draft.
- Allocation: FIFO order, FEFO order, min shelf life excluded, specific batch, never from dock/staging/quarantine/yard/blocked, partial + backorder, available reduced immediately, cancel releases reservations.
- Picking: route order; wrong bin/item/batch rejected; serial must be at the stock key; pick posting consumes reservation and moves to staging; short pick releases remainder, creates count request, reallocation adds tasks.
- Dispatch: all staged scanned or marked not loaded; not-loaded moved back; dispatch posting removes staging stock; serials → dispatched; idempotent confirm; DO becomes read-only.
- Gate pass issued and exit verified once.
- POD moves DO to delivered.
- Return GRN pre-filled from DO; returned serials back in stock.
- Delivery note PDF contains lines, batches and serials.
- Scanner Pick and Dispatch with `request_id` idempotency.
- Notifications sent to the right users.
- `wms:stock:verify` clean after full outbound flow.
- `mysql` group: two DOs allocating the last stock at the same time (only one gets it); two pickers on the same task (only one succeeds); double dispatch confirm posts once.
- Permissions per role; guard tests pass.

## Step 16 - Manual test checklist
Write `docs/testing/module-06.md`: department user creates DO → approval → allocation (FIFO and FEFO) → pick list → scanner pick (normal, wrong item, short) → dispatch with load scan → delivery note and gate pass → gate exit → POD → return. Check Stock on Hand, Movement History and Serial Lookup at each step.

## Deliverable
Tables, enums, workflow, allocation algorithm, services, screens, permission matrix, files changed, test results (`php artisan test` 0 failures, `mysql` group, `npm run build`), decisions taken.

Stop when done. Do not start Module 7.
