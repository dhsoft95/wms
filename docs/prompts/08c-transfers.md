# Phase C: Transfers

Read PROJECT-BRIEF.md and docs/BUILD-CONVENTIONS.md first.
All stock changes go through StockLedger. Reuse internal movements (MOV), changeOwner, the Orders page, PurchaseOrderWorkflow, allocation, picking, dispatch, DN/RCN, GRN and putaway. Do not rebuild them.
Step 1: short plan, wait for my confirmation.

## 1. Orders page integration
- New Order > Transfer opens a second step "Select transfer type":
  In-warehouse transfer | Departmental transfer | Warehouse transfer.
- Transfers tab on the Orders page lists all three types: number, type badge, from, to, department, status.
- Sidebar: remove the Warehouse Operations menu if fully covered by Orders, otherwise rename it to Transfers.

## 2. Who creates, accepts and executes
- All transfer types are created only by department-user (same rule as all orders).
  Warehouse staff cannot create (403 on create route, New Order button hidden).
- In-warehouse transfer: storekeeper or warehouse-officer executes the move. No extra approval.
- Departmental transfer: receiving department (department-user of the target VW) or warehouse-manager must accept before stock changes owner.
- Warehouse transfer:
    - Different department: receiving department or warehouse-manager must accept before picking starts.
    - Same department: follows item dispatch approval rules (Phase A).
- Category users see and approve only their categories (existing scope).
- Notifications (in-app and email):
    - Receiving department when acceptance is needed.
    - Warehouse staff when a transfer is ready to execute.
    - Creator when the transfer is accepted, rejected or completed.

## 3. Transfer types

### a. In-warehouse transfer (MOV)
- Same warehouse, same department. Location changes, owner does not.
- Flow: Submitted (department-user) -> Completed (storekeeper/warehouse-officer executes: scan or select from-bin, item, qty, serials, to-bin).
- Use the existing internal movement posting (StockLedger::move).
- Remove the scanner "quick move" that creates a movement without a department request.

### b. Departmental transfer (TRF)
- Internal (virtual): ownership changes only, stock stays in the same bin (StockLedger::changeOwner).
  Flow: Submitted -> Accepted -> Completed (posts on acceptance).
- External (physical): ownership changes AND stock moves to a bin for the new department.
  Flow: Submitted -> Accepted -> Picked -> Put away -> Completed.
  changeOwner + move run in one transaction at completion.
- Rejected at any point before posting ends the transfer with a reason.

### c. Warehouse transfer (WTR, add document sequence)
- From warehouse A to warehouse B, same or different department.
- Flow at source: Submitted -> Accepted (if needed) -> Allocated -> Picking -> Dispatched (DN + RCN printed).
- On dispatch, the system creates an inbound PurchaseOrder at warehouse B automatically:
  source = Warehouse Transfer, same lines, batches, expiry and serials, linked to the transfer.
  This system-created PO is allowed even though users of warehouse B did not create it.
- Stock is In Transit between dispatch and receipt (not available at A or B). Show in-transit qty on Stock on Hand.
- Warehouse B receives through the normal GRN and putaway. Variance follows item thresholds.
- On receipt the transfer becomes Completed. If the department differs, ownership changes to the target VW on receipt.
- Both documents link to each other.

## 4. Other changes
- Putaway: do not suggest a location. User selects or scans the bin (bin-only rules still apply).
- Transfers list: rename the "From" column to "Department".
- Receiving orders list: remove the manual status buttons. Status changes only through the workflow.

## 5. Rules
- Only level = bin locations hold stock. Source and destination must be active and not blocked.
- Qty cannot exceed available (on hand minus reserved). Re-check with lock at posting.
- Serials must exist at the source and carry over to the destination.
- Bins under an open cycle count are blocked.
- Quarantine stock cannot be transferred (use the quarantine flow).
- VW and category scope apply to all transfer documents.
- Every post is idempotent: mov:{id}:post, trf:{id}:post, wtr:{id}:dispatch, wtr:{id}:receive.

## 6. Permissions
- transfers.view
- transfers.create (department-user only)
- transfers.execute (storekeeper, warehouse-officer)
- transfers.accept (department-user of the target VW, warehouse-manager)
  Print the updated role matrix.

## 7. Tests (Pest)
- Only department-user can create any transfer type; storekeeper and warehouse-officer get 403
- Storekeeper/warehouse-officer can execute an in-warehouse transfer created by a department-user
- In-warehouse: location changes, owner stays the same
- Departmental virtual: owner changes, location same, only after acceptance
- Departmental physical: owner and location change, reserved stock blocked
- Rejected transfer does not move stock
- Warehouse transfer: inbound PO auto-created at destination on dispatch, in-transit qty correct, serials carried over, receipt completes the transfer
- Different-department warehouse transfer needs acceptance; same department follows item rules
- Ownership changes on receipt when departments differ
- Quarantine stock and bins under count are blocked
- Putaway without suggestion still blocks non-bin locations
- Transfers tab shows all three types

## Deliverable
Run only the new/changed tests (php artisan test --filter=Transfer). Do not run the full suite or the MySQL group.
Short report: files changed, role matrix, test pass/fail for the filtered tests.
Stop when done.
