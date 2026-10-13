# Module 04 — Receiving / GRN + Putaway Manual Test Checklist

## Prerequisites
- Run `php artisan migrate && php artisan db:seed --class=RolePermissionSeeder`
- Run `php artisan db:seed --class=ReceivingDemoSeeder` for demo data
- Log in as system-admin user

---

## 1. Schema Additions

- [ ] `warehouses` table has `default_receiving_dock_location_id` and `default_quarantine_location_id` columns
- [ ] `items` table has `preferred_zone_id` column
- [ ] `grns`, `grn_lines`, `grn_receipts`, `grn_receipt_serials`, `grn_variance_approvals` tables exist
- [ ] `putaway_tasks` table exists
- [ ] `lpns` table has `warehouse_id`, `current_location_id`, `grn_id`, `printed_at`, `print_count`

## 2. Warehouse Edit — Receiving Defaults

- [ ] Navigate to Admin → Warehouses → Edit any warehouse
- [ ] A "Receiving Defaults" card appears after the main details card
- [ ] "Default Receiving Dock" dropdown shows only `receiving_dock` type locations for this warehouse
- [ ] "Default Quarantine Location" dropdown shows only `quarantine` type locations
- [ ] Save and verify values persist in the DB

## 3. Item Edit — Preferred Zone

- [ ] Navigate to Admin → Items → Edit any item → Tracking tab
- [ ] "Preferred Putaway Zone" select appears below the tracking checkboxes
- [ ] Dropdown shows locations with `level = zone`
- [ ] Save and verify `preferred_zone_id` is stored on the item

## 4. GRN Index

- [ ] Navigate to Inbound → Receiving (GRN)
- [ ] Page shows paginated list with status tabs
- [ ] Status tabs show counts for each status
- [ ] Search by GRN number, container, or supplier filters results
- [ ] Source filter dropdown works
- [ ] "View" and "Receive" row buttons appear based on status

## 5. Create GRN

- [ ] Click "New GRN" button
- [ ] Form shows Source radio buttons (Supplier, Return, Manual)
- [ ] Selecting a Warehouse auto-fills the dock from warehouse defaults
- [ ] Supplier field appears/hides based on source selection
- [ ] Submitting creates a GRN and redirects to Show page

## 6. GRN Show

- [ ] Page shows status stepper with correct current step highlighted
- [ ] Status badges match the current GRN state
- [ ] All tabs render: Lines, Receipts, LPNs, Putaway Tasks, Variance Approvals, Activity
- [ ] "Start Receiving" button appears for draft GRNs
- [ ] "Receive Goods" link appears for draft/receiving GRNs
- [ ] "Cancel" button works and transitions to Cancelled
- [ ] Activity log shows status changes

## 7. Receive Screen

- [ ] Navigate to a Draft/Receiving GRN → click "Receive Goods"
- [ ] Auto-transitions Draft → Receiving on mount
- [ ] Scan input is auto-focused
- [ ] Entering a SKU and pressing Enter highlights the matching line
- [ ] Selecting a line shows the receipt entry form with UoM, batch, expiry, condition, LPN fields
- [ ] Batch/expiry fields only appear for items with tracking enabled
- [ ] "Add Receipt" button saves a receipt and updates the line progress
- [ ] "Delete Receipt" removes the receipt and recalculates line totals
- [ ] "Generate LPN" creates a new LPN and selects it
- [ ] "Finish Receiving" with no variance confirms the GRN and posts stock
- [ ] "Finish Receiving" with variance sends to PendingVarianceApproval

## 8. Variance Approval

- [ ] GRN in `pending_variance_approval` shows the amber approval panel on Show page
- [ ] Users without `grn.variance.approve` see "Awaiting approval" message, not the action buttons
- [ ] Users with `grn.variance.approve` see "Approve" and "Reject" buttons
- [ ] Approving transitions to `received` and posts stock
- [ ] Rejecting transitions back to `receiving` with an activity log entry

## 9. Putaway Tasks

- [ ] After confirming a GRN, putaway tasks are created (one per receipt)
- [ ] Navigate to Inbound → Put-Away
- [ ] Tasks appear in the list with correct status, item, qty, from-location, suggested location
- [ ] Status tabs and counts work correctly
- [ ] "My Tasks Only" toggle filters to current user's assigned tasks
- [ ] Selecting tasks and clicking "Assign" opens the bulk assign modal
- [ ] Assigning tasks updates status to `assigned`
- [ ] "Execute" button opens the execution modal
- [ ] Scanning a bin code in the modal resolves to a Location record
- [ ] Clicking "Confirm Putaway" executes the stock move and marks task as completed

## 10. LPN Print

- [ ] On the GRN Show page (LPNs tab), click "Print" for any LPN
- [ ] A new browser tab opens with the A4 print page
- [ ] Code128 barcode renders as SVG inline
- [ ] LPN code appears below the barcode
- [ ] GRN number and warehouse name appear in the label
- [ ] "Print Labels" button triggers the browser print dialog
- [ ] `printed_at` and `print_count` are updated in the database

## 11. Permissions

- [ ] `department-user` can see the GRN list (grn.view) but cannot create
- [ ] `storekeeper` can create and receive GRNs but cannot approve variances
- [ ] `warehouse-manager` can approve variances (grn.variance.approve)
- [ ] `viewer-auditor` can view GRNs but cannot perform any write operations

## 12. Navigation

- [ ] "Receiving (GRN)" appears in the Inbound group
- [ ] "Put-Away" appears in the Inbound group, after Receiving (GRN)
- [ ] Put-Away no longer appears in Warehouse Operations

## 13. Container Integration

- [ ] Starting unloading of a container creates a GRN with `source = container`
- [ ] GRN lines are copied from container expected lines
- [ ] Confirming all GRNs for a container auto-triggers container markUnloaded

## 14. Automated Tests

```bash
# SQLite tests
php artisan test --filter=GrnWorkflow

# MySQL concurrency tests (requires phpunit.mysql.xml)
php artisan test --filter=GrnConcurrency --configuration phpunit.mysql.xml
```

Expected: All GrnWorkflow tests pass. GrnConcurrency tests skip on SQLite.
