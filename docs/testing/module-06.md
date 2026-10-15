# Module 06 — Delivery Orders, Picking & Dispatch — Manual Test Checklist

> **Audience:** Warehouse team executing pre-go-live UAT.
> **How to use:** Work through each section top to bottom. Tick every box before signing off the module. Any box that cannot be ticked is a defect — log it with the section number and a short description.

---

## 0. Pre-conditions

Run these commands before starting any test. Re-run after a database reset.

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=OutboundDemoSeeder
```

### Seed data to verify before testing

- [ ] At least two Virtual Warehouses (VWs) exist, each with a staging location of type `staging`
- [ ] At least one warehouse has `default_staging_location_id` set to a `staging` location
- [ ] Stock on hand exists in `storage` locations for at least three different items — verify on the Stock on Hand screen
- [ ] At least one item has batch/expiry tracking enabled, with two batches: one expiring soon (within 30 days) and one with a longer shelf life
- [ ] At least one item has serial tracking enabled, with serials already in stock
- [ ] Partner records exist for at least two consignees
- [ ] The following roles exist and have users assigned: `clerk` / `store-officer`, `picker`, `supervisor` / `department-head`, `warehouse-manager`, `gate-security`, `system-admin`
- [ ] Settings → Operations → Outbound section exists in the admin panel

### Settings to configure before testing

- [ ] Navigate to Settings → Operations → Outbound
- [ ] Set **Approval mode** to `both` (two-level) for the main warehouse — this tests the full workflow
- [ ] Set **Allow partial allocation** to `yes`
- [ ] Set **Auto backorder** to `yes`
- [ ] Set **FEFO minimum remaining shelf life** to `14` days
- [ ] Set **Short pick behaviour** to `reallocate`
- [ ] Save settings and confirm the values persist after a page reload
- [ ] Change approval mode to `none` on a second warehouse to test the simplified path separately

---

## 1. Schema Verification

Confirm all new tables and columns are present before running functional tests.

- [ ] `delivery_orders` table exists with columns: `number`, `virtual_warehouse_id`, `department_id`, `consignee_partner_id`, `delivery_address`, `required_date`, `priority`, `allocation_rule`, `status`, `requested_by`, `submitted_at`, `remarks`, `backorder_of_id`, `warehouse_id`, soft-delete column `deleted_at`
- [ ] `delivery_order_lines` table exists with columns: `delivery_order_id`, `line_no`, `item_id`, `uom_id`, `requested_qty`, `requested_qty_base`, `requested_batch_id`, `allocated_qty`, `picked_qty`, `short_qty`, `dispatched_qty`, `status`, `notes`
- [ ] `delivery_order_approvals` table exists with columns: `delivery_order_id`, `level`, `approver_role`, `decided_by`, `decision`, `notes`, `decided_at`
- [ ] `delivery_order_allocations` table exists with columns: `delivery_order_line_id`, `stock_balance_id`, `stock_reservation_id`, `item_id`, `batch_id`, `lpn_id`, `location_id`, `qty`, `status`
- [ ] `pick_lists` table exists with sequence prefix `PCK`, columns: `number`, `delivery_order_id`, `status`, `assigned_to`, `started_at`, `completed_at`
- [ ] `pick_tasks` table exists with columns: `pick_list_id`, `delivery_order_allocation_id`, `sequence`, `location_id`, `item_id`, `batch_id`, `lpn_id`, `qty_to_pick`, `qty_picked`, `qty_short`, `status`, `picked_by`, `picked_at`, `stock_transaction_id`, `short_reason_code_id`
- [ ] `pick_task_serials` pivot table exists linking pick tasks to serial numbers
- [ ] `dispatches` table exists with sequence prefix `DSP`, columns: `number`, `delivery_order_id`, `vehicle_no`, `driver_name`, `driver_phone`, `driver_id_no`, `transporter_partner_id`, `seal_no`, `dispatched_at`, `dispatched_by`, `stock_transaction_id`, `notes`
- [ ] `dispatch_items` table exists with columns: `dispatch_id`, `lpn_id`, `item_id`, `batch_id`, `qty`, `scanned` flag
- [ ] `gate_passes` table exists with sequence prefix `GP`, columns: `number`, `dispatch_id`, `status`, `exited_at`, `verified_by`
- [ ] `proof_of_deliveries` table exists with columns: `delivery_order_id`, `received_by_name`, `received_at`, `notes`, `signature_path`, `uploaded_by`
- [ ] `stock_count_requests` table exists with columns: `location_id`, `item_id`, `reason`, `reference_type`, `reference_id`, `requested_by`, `status`
- [ ] `grns` table has a `delivery_order_id` nullable column (for return GRN linking)
- [ ] `locations` table has a `pick_sequence` integer column
- [ ] `warehouses` table has a `default_staging_location_id` column

---

## 2. Navigation & Page Shell

- [ ] **Outbound → Orders** appears in the left navigation under an "Outbound" group
- [ ] **Outbound → Pick Lists** appears in the Outbound group
- [ ] **Outbound → Dispatch & Gate** appears in the Outbound group
- [ ] **Outbound → Proof of Delivery** appears in the Outbound group
- [ ] Clicking each nav item loads the correct page (no 404, no blank screen)
- [ ] All four pages render in both light mode and dark mode without layout breaks
- [ ] The Outbound group is not visible to users with no outbound permissions (e.g., a receiving-only storekeeper)

---

## 3. Delivery Orders — Create & Edit

### 3.1 Create a new Delivery Order

- [ ] Log in as a `clerk` / `store-officer` assigned to Virtual Warehouse A
- [ ] Navigate to Outbound → Orders → click **New Order**
- [ ] The form shows: Department/VW selector (pre-filled to user's VW), Consignee partner search, Delivery address, Required date, Priority (Low / Normal / High / Urgent), Allocation rule (Default / FIFO / FEFO), Remarks
- [ ] The VW selector shows **only the user's own VW** — other VWs are not selectable
- [ ] Add line 1: search for an item — the **available qty** for the selected VW is shown next to the item in the dropdown
- [ ] Set qty and UoM for line 1
- [ ] Add a second line for a different item
- [ ] Set Required date to today and Priority to **High**
- [ ] Submit the form — the DO is created with status `draft` and a document number starting with `DO`
- [ ] The DO Show page opens automatically after creation
- [ ] The status stepper shows "Draft" as the active step

### 3.2 Edit a draft Delivery Order

- [ ] From the DO Show page, click **Edit**
- [ ] Change the required date to a past date (to test overdue display later)
- [ ] Change the priority to **Urgent**
- [ ] Add a third line to the order
- [ ] Delete line 2 using the row delete button
- [ ] Save — changes are reflected on the Show page
- [ ] Verify the activity log tab shows an "Updated" entry

### 3.3 VW scoping

- [ ] Log in as a `clerk` assigned to Virtual Warehouse B
- [ ] Attempt to directly visit the Show URL of a DO belonging to VW A — the page returns a 403 or redirects to the orders list
- [ ] The Orders list shows only VW B's DOs for this user
- [ ] Log in as `warehouse-manager` — the Orders list shows DOs from all VWs
- [ ] Confirm the VW filter dropdown is available and works for the warehouse-manager view

### 3.4 Overdue filter

- [ ] On the Orders list, click the **Overdue** filter tab or apply the overdue filter
- [ ] The DO edited above (past required date, status `draft`) appears in the results
- [ ] Required date column for overdue DOs is displayed in red
- [ ] A dispatched or delivered DO that is past required date does NOT appear in the overdue filter

---

## 4. Delivery Orders — Workflow Transitions

### 4.1 Submit (no approval required — approval mode: none)

- [ ] Switch the test warehouse's approval mode to `none` in Settings
- [ ] Log in as `clerk`, create a new DO for this warehouse
- [ ] Click **Submit** — a confirmation prompt appears with the DO number
- [ ] Confirm submission — status jumps directly to `approved` (skipping the approval step)
- [ ] The "Submit" button is no longer visible
- [ ] Activity log shows "Submitted" and "Auto-approved (approval mode: none)"

### 4.2 Submit (two-level approval — approval mode: both)

- [ ] Switch back to `both` approval mode for the main warehouse
- [ ] Log in as `clerk`, create and submit a DO
- [ ] Status changes to `submitted`
- [ ] **Approvals Inbox** notification count increments for the `supervisor` / `department-head` user
- [ ] Log in as `department-head`, go to Approvals Inbox — the submitted DO appears under Level 1
- [ ] Approve with a note — status changes to a pending-level-2 state; DO is now waiting for warehouse-manager approval
- [ ] Log in as `warehouse-manager`, go to Approvals Inbox — the DO appears under Level 2
- [ ] Approve with a note — status changes to `approved`
- [ ] Activity log on the DO shows both approval decisions with timestamps and approver names

### 4.3 Reject at Level 1

- [ ] Submit a new DO through the clerk user
- [ ] Log in as `department-head`, reject the Level 1 approval with a rejection reason
- [ ] Status returns to `draft`
- [ ] The clerk receives a notification that the DO was rejected
- [ ] The clerk can edit and resubmit the DO

### 4.4 Reject at Level 2

- [ ] Submit a DO and approve it at Level 1
- [ ] Log in as `warehouse-manager`, reject the Level 2 approval with a reason
- [ ] Status returns to `draft`
- [ ] The requester is notified of the rejection with the warehouse-manager's reason

### 4.5 Cancel a Draft DO

- [ ] Log in as `clerk`, open a DO in `draft` status
- [ ] Click **Cancel** — a modal asks for a cancellation reason
- [ ] Enter a reason and confirm — status changes to `cancelled`
- [ ] The cancelled DO is soft-deleted (does not appear in the main list by default)
- [ ] A "Show cancelled" toggle or filter reveals it
- [ ] A cancelled draft DO cannot be re-opened

### 4.6 Cancel a submitted DO

- [ ] Log in as `warehouse-manager`
- [ ] Find a DO in `submitted` status and cancel it — the action must be permitted for managers
- [ ] Confirm the status changes to `cancelled` and the activity log records the cancellation with the manager's user name

---

## 5. Allocation

### 5.1 Basic FIFO allocation

- [ ] Ensure a DO is in `approved` status with lines covering items that have stock in storage locations
- [ ] Log in as `warehouse-manager` or `store-officer` with `orders.allocate` permission
- [ ] Click **Allocate** on the DO Show page — a progress indicator appears while allocation runs
- [ ] After allocation, status changes to `allocated`
- [ ] The **Allocation tab** on the DO Show page lists each allocation: item, batch, LPN (if any), location, qty reserved
- [ ] Open the **Stock on Hand** screen, find the allocated item, and confirm **Available qty = On-hand qty − Allocated qty** (available has decreased)
- [ ] Allocation rows in `delivery_order_allocations` have `status = reserved`
- [ ] Corresponding `stock_reservations` rows exist with the correct qty

### 5.2 FEFO allocation

- [ ] Create a DO with the allocation rule set to `FEFO` for an expiry-tracked item
- [ ] The item has two batches: Batch A expiring in 60 days and Batch B expiring in 10 days
- [ ] FEFO minimum shelf life is set to 14 days in Settings
- [ ] Run allocation — Batch B (10 days, below minimum) must be **skipped**; Batch A (60 days) is allocated
- [ ] Verify the Allocation tab shows Batch A, not Batch B
- [ ] Change minimum shelf life to 5 days in Settings and re-allocate (after releasing) — Batch B should now be included

### 5.3 Partial allocation and auto backorder

- [ ] Create a DO requesting qty 100 of an item that only has 60 units in stock across all storage locations
- [ ] Run allocation with partial allocation enabled and auto backorder enabled
- [ ] Status changes to `partially_allocated`
- [ ] 60 units are reserved; the Allocation tab shows qty 60 allocated
- [ ] A new DO is automatically created in `approved` status with qty 40 (the backorder remainder)
- [ ] The backorder DO has `backorder_of_id` pointing to the original DO's ID
- [ ] A link or badge on the original DO show page indicates a backorder was created
- [ ] The backorder DO shows "Backorder of DO-XXXXX" in its header

### 5.4 Allocation respects location exclusions

- [ ] Confirm allocation never draws from locations of type `dock`, `staging`, `quarantine`, or `yard`
- [ ] Create a test item with stock only in a quarantine location — running allocation on a DO for that item results in `partially_allocated` with 0 qty (or a clear error message) because no eligible stock exists
- [ ] Stock in a location marked as blocked (`is_active = false`) is also excluded

### 5.5 Release allocation

- [ ] Find a DO in `allocated` or `partially_allocated` status (before pick list is generated)
- [ ] Log in as `warehouse-manager`, click **Release Allocation**
- [ ] Status returns to `approved`
- [ ] `delivery_order_allocations` rows are removed or set to `released`
- [ ] The corresponding stock reservations are also removed — available qty on Stock on Hand screen increases back to its pre-allocation value

### 5.6 Re-allocate

- [ ] On a DO in `approved` status (allocation released), click **Allocate** again
- [ ] Allocation runs fresh and the DO returns to `allocated`
- [ ] The new allocations may differ from the previous ones if stock has changed

---

## 6. Pick Lists

### 6.1 Generate a pick list

- [ ] Navigate to a DO in `allocated` status
- [ ] Click **Generate Pick List** — a `PCK-XXXXX` pick list is created
- [ ] The DO status changes to `picking`
- [ ] Navigate to Outbound → Pick Lists — the new pick list appears with status `open`
- [ ] Click into the pick list — tasks are listed one per allocation, sorted by pick sequence (location order)
- [ ] Each task row shows: sequence number, location code, item name, batch (if applicable), LPN (if applicable), qty to pick

### 6.2 Assign a picker

- [ ] On the pick list, select one or more tasks using the checkboxes
- [ ] Click **Assign** — a modal appears with a user dropdown showing only users with `picking.execute` permission
- [ ] Select a picker and confirm — selected tasks change to `assigned` status
- [ ] Log in as the assigned picker and navigate to Outbound → Pick Lists → "My Pick Lists"
- [ ] The pick list appears in the list

### 6.3 Desktop pick execution

- [ ] Log in as a picker, open the assigned pick list
- [ ] Click **Execute** on a task — a pick execution modal opens
- [ ] Modal shows: location code, item name, batch, LPN, qty to pick
- [ ] Enter a qty equal to the full qty to pick — click **Confirm Pick**
- [ ] Task status changes to `picked`
- [ ] Pick list status changes to `in_progress` on the first completed task
- [ ] Complete all remaining tasks in the pick list
- [ ] Pick list status changes to `completed`
- [ ] DO status changes to `picked`
- [ ] On the Stock on Hand screen, the item's on-hand qty in the source bin has decreased; the staging location now shows the picked qty
- [ ] The Movement History screen shows a `pick` type ledger entry for each task

### 6.4 Short pick (desktop)

- [ ] Generate a new pick list; open a task
- [ ] In the execution modal, enter a qty **less than** the qty to pick
- [ ] Select a short reason code from the dropdown
- [ ] Click **Confirm Pick**
- [ ] Task status changes to `short`
- [ ] The short qty is recorded on the `pick_tasks` row
- [ ] The reservation for the missing qty is released — available qty on Stock on Hand increases for that missing amount
- [ ] A `stock_count_requests` row is created for the bin and item with `reason = short_pick`
- [ ] A notification is sent to warehouse-managers about the short pick
- [ ] Because short pick behaviour is `reallocate`: the system attempts to allocate the missing qty from another bin; if successful, a new pick task is added to the same pick list

### 6.5 Scanner pick flow — normal execution

- [ ] Log in on a scanner device (or use a browser in scanner mode) with `scan.pick` permission
- [ ] From the Scanner Home, tap the **Pick** tile
- [ ] Scan or enter the pick list number (`PCK-XXXXX`) — the first task loads, showing the bin location in large text
- [ ] Navigate to the correct bin in the warehouse
- [ ] Scan the bin barcode — a success beep and green flash confirm the bin matches
- [ ] Scan the item barcode — confirms the correct item
- [ ] Enter the pick qty on the keypad — tap **Confirm**
- [ ] A success animation plays; the next task loads automatically
- [ ] After the last task, a "Pick list complete" screen appears
- [ ] Verify the pick list status is `completed` on the desktop view

### 6.6 Scanner pick — wrong bin rejection

- [ ] Start a scanner pick session; when prompted to scan a bin, scan the **wrong** bin barcode
- [ ] An error beep and red flash are shown — "Wrong location — expected BIN-XXXX"
- [ ] The task remains open; the picker is prompted to scan again
- [ ] Scanning the correct bin clears the error and proceeds normally

### 6.7 Scanner pick — wrong item rejection

- [ ] At the item scan step, scan a barcode for a different item
- [ ] Error feedback is shown: "Wrong item — expected SKU-XXXX"
- [ ] The task remains open; the picker must scan the correct item barcode
- [ ] Scanning the correct item proceeds normally

### 6.8 Scanner pick — serial item handling

- [ ] Ensure the pick task is for a serial-tracked item
- [ ] After confirming the bin and item, a "Scan serials" prompt appears
- [ ] Scan each serial barcode individually — each scan adds the serial to a confirmation list
- [ ] Scanning a serial that is not in stock at the current stock key (location + batch) shows an error
- [ ] Scanning a serial that is already picked (in another task) shows an error
- [ ] After scanning all required serials, tap **Confirm** — the task completes and serials are recorded in `pick_task_serials`

### 6.9 Scanner pick — idempotency

- [ ] During a pick confirmation, simulate a double-tap or double-submit (submit the same `request_id` twice)
- [ ] Only one ledger posting is created — the second submission is silently ignored (no error, no duplicate)
- [ ] Task qty_picked is correct (not doubled)

---

## 7. Dispatch

### 7.1 Dispatch modal — basic flow

- [ ] Navigate to a DO in `picked` status (all tasks completed)
- [ ] Click **Dispatch** — a dispatch modal opens
- [ ] The modal shows: items staged for this DO (item, batch, LPN, qty), and input fields for: Vehicle No., Driver Name, Driver Phone, Driver ID (optional), Transporter (optional partner search), Seal No. (optional), Notes
- [ ] Fill in Vehicle No. and Driver Name (minimum required fields)
- [ ] Click **Confirm Dispatch**
- [ ] A `DSP-XXXXX` dispatch record is created
- [ ] DO status changes to `dispatched`
- [ ] Stock is removed from the staging location — Stock on Hand confirms qty in staging is now 0 for this DO's items
- [ ] Movement History shows a `dispatch` type ledger entry

### 7.2 Gate pass creation on dispatch

- [ ] After confirming dispatch (7.1), confirm a gate pass `GP-XXXXX` is automatically created
- [ ] Gate pass status is `issued`
- [ ] Navigate to Outbound → Dispatch & Gate — the dispatch appears in the recent dispatches list with the GP number

### 7.3 Print Delivery Note (Goods Delivery Note PDF)

- [ ] On the dispatched DO or Dispatch record, click **Print Delivery Note**
- [ ] A PDF opens in a new tab
- [ ] PDF contains: company header and logo, DO number, Dispatch number, date, consignee name and delivery address
- [ ] PDF lines show: line number, SKU, item description, batch, expiry date (if applicable), qty, UoM
- [ ] If serial-tracked items were dispatched, serial numbers are listed per line
- [ ] Vehicle number, driver name, and seal number appear in the footer
- [ ] Signature blocks are present: "Dispatched by", "Driver / Carrier", "Received by"
- [ ] The gate pass number and QR code are included on the PDF

### 7.4 Print dispatch labels

- [ ] On the Dispatch record, click **Print Dispatch Labels**
- [ ] The Module 5 `dispatch` label template is used
- [ ] Labels show DO number, dispatch number, and consignee
- [ ] The print job is recorded in `label_print_jobs`

### 7.5 Desktop load scan (dispatch items verification)

- [ ] On a DO in `picked` status, open the Dispatch screen
- [ ] The "Items staged" table lists every LPN/item expected to be loaded
- [ ] Each row has a "Scanned" indicator (initially unchecked)
- [ ] Scan or manually mark each LPN as loaded — the row updates to "Scanned"
- [ ] Mark one LPN as **Not Loaded** — a confirmation prompt appears
- [ ] Confirm "Not Loaded" — that LPN's stock is moved back from staging to its source bin via the ledger (a `relocation` entry appears in Movement History)
- [ ] The not-loaded LPN's dispatched qty is reduced on the DO line
- [ ] Click **Confirm Dispatch** with remaining scanned items — only the loaded items are included in the dispatch record

### 7.6 Scanner Dispatch flow

- [ ] Log in on a scanner device with `scan.dispatch` permission
- [ ] From Scanner Home, tap the **Dispatch** tile
- [ ] Scan the DO barcode (DO number) — the DO details load (consignee, items expected)
- [ ] For each LPN staged for this DO, scan the LPN barcode — each successful scan is confirmed with a beep and added to the loaded list
- [ ] Scanning an LPN that does not belong to this DO shows an error
- [ ] After all expected LPNs are scanned (or items marked not loaded), a **Confirm Dispatch** button becomes active
- [ ] Enter vehicle and driver details on the scanner form
- [ ] Tap **Confirm Dispatch** — dispatch is posted; a success screen shows the GP number

### 7.7 Dispatch idempotency

- [ ] Submit a dispatch confirmation request with a specific `request_id`
- [ ] Immediately submit the same `request_id` again (simulating a double-tap)
- [ ] Only one dispatch record and one ledger posting are created
- [ ] The DO status does not change a second time
- [ ] Verify in `dispatches` table that only one row exists for the DO

### 7.8 Dispatched order is read-only

- [ ] Open a DO in `dispatched` status
- [ ] Confirm there is no Edit button, no Allocate button, no Cancel button
- [ ] The DO header fields cannot be changed
- [ ] The only actions available are: Print Delivery Note, Create Return, Upload POD

---

## 8. Gate Check

### 8.1 Desktop Gate Check — verify exit

- [ ] Log in as `gate-security` or any user with `gate.verify` permission
- [ ] Navigate to Outbound → Dispatch & Gate → Gate Check tab (or Gate Check page)
- [ ] Enter the GP number manually (e.g., `GP-00001`) and press Enter
- [ ] The gate pass details are displayed: vehicle number, driver name, DO number, consignee, items summary
- [ ] Confirm the details match the printed delivery note
- [ ] Click **Confirm Exit** — gate pass status changes to `exited`; `exited_at` is recorded
- [ ] The gate pass can no longer be used to exit again — attempting to verify it a second time shows "Already exited on [date/time]"

### 8.2 Desktop Gate Check — invalid GP number

- [ ] Enter a GP number that does not exist — an error message "Gate pass not found" is shown
- [ ] Enter a GP number for an already-exited gate pass — the status "Already exited" message is shown with the exit timestamp

### 8.3 Scanner Gate Check

- [ ] Log in on a scanner device with `scan.gate` permission
- [ ] The Scanner Home shows a **Gate Check** tile (only if the user has `scan.gate`)
- [ ] Tap Gate Check — a scan input appears
- [ ] Scan the QR code from the printed delivery note (which encodes the GP number)
- [ ] Vehicle, driver, DO number, and item summary are displayed on the scanner screen
- [ ] Tap **Confirm Exit** — success beep and flash; gate pass is marked `exited`
- [ ] The same gate pass cannot be exited again via scanner — an error is shown on re-scan

### 8.4 Gate Check without permission

- [ ] Log in as a user with no `gate.verify` or `scan.gate` permission
- [ ] The Gate Check tile does NOT appear on the Scanner Home
- [ ] Attempting to visit the Gate Check URL directly returns a 403 error

---

## 9. Proof of Delivery

### 9.1 Access POD page

- [ ] Navigate to Outbound → Proof of Delivery
- [ ] The page lists dispatched DOs that have not yet received a POD, with the number of days since dispatch
- [ ] DOs are sorted by oldest dispatch first (most overdue at the top)
- [ ] Filter by consignee name — results narrow correctly
- [ ] Filter by date range — results narrow to DOs dispatched within that range

### 9.2 Record POD

- [ ] Click **Upload POD** for a dispatched DO (or open the DO and go to the Proof of Delivery tab)
- [ ] An upload modal appears with fields: Received By (name), Received At (date/time), Notes
- [ ] Fill in all fields and click **Save** (without uploading a file)
- [ ] The POD record is created; DO status changes to `delivered`
- [ ] The POD tab on the DO Show page shows the recorded details
- [ ] The DO no longer appears in the "awaiting POD" list on the main POD page

### 9.3 Upload POD file

- [ ] Open the POD upload modal again (for a different dispatched DO)
- [ ] Fill in the received-by details and also attach a file (JPEG or PDF of a signed delivery note)
- [ ] Confirm the file upload — the file is stored in private storage
- [ ] On the DO Show page (POD tab), a download/preview link appears for the uploaded file
- [ ] Clicking the link opens or downloads the file correctly
- [ ] The `signature_path` column on `proof_of_deliveries` is populated with the file path

### 9.4 POD condition badges

- [ ] If the received-by details include notes describing a damaged delivery, a "Notes" badge or icon appears on the DO list to indicate POD was recorded with remarks
- [ ] A DO with a clean POD (no notes) shows no badge or a "Delivered" green badge
- [ ] A DO awaiting POD shows an "Awaiting POD" amber badge

### 9.5 POD upload via scanner (signature pad)

- [ ] On a touch-capable scanner device, open the Gate Check or POD page
- [ ] A signature pad canvas appears — draw a signature using touch input
- [ ] Tap **Save Signature** — the signature is captured and linked to the POD record
- [ ] The signature image appears on the DO Show page (POD tab)

---

## 10. Returns (Return GRN from DO)

### 10.1 Create a return GRN

- [ ] Open a DO in `dispatched` or `delivered` status
- [ ] Click **Create Return**
- [ ] A new GRN creation screen opens with `source = return` pre-selected
- [ ] The GRN is linked to the DO — `delivery_order_id` on the GRN is set
- [ ] GRN lines are pre-filled with items from the dispatched DO lines, showing the dispatched qty and batch
- [ ] If items were serial-tracked, the serial numbers from the dispatch are listed for selection
- [ ] Adjust quantities if only a partial return is needed
- [ ] Save the return GRN — it is created in `draft` status

### 10.2 Receive the return GRN

- [ ] Navigate to Inbound → Receiving (GRN) and find the return GRN
- [ ] The GRN source column shows "Return" and the DO number is linked
- [ ] Proceed through the normal Module 4 receiving flow: Start Receiving → Receive Goods → Confirm
- [ ] After confirming, stock is posted back to the receiving dock location
- [ ] For serial items: scan the returned serials during receiving; serials should move from `dispatched` status back to `in_stock`
- [ ] Movement History shows a `receipt` type ledger entry referencing the return GRN
- [ ] The return GRN appears on the DO Show page (Dispatch tab or Returns section) as a linked document

---

## 11. Permissions Matrix

Verify each role can perform exactly the actions listed and no others. Test by logging in as a user in that role and attempting each action.

| Action | clerk / store-officer | picker | supervisor / dept-head | warehouse-manager | gate-security | system-admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `orders.view` — view DO list and Show page | own VW only | no | own dept only | all VWs | no | yes |
| `orders.create` — New Order form | own VW only | no | own dept only | yes | no | yes |
| `orders.submit` — Submit button on draft DO | own VW only | no | own dept only | yes | no | yes |
| `orders.approve.department` — Level 1 approval in Inbox | no | no | yes (own dept) | yes | no | yes |
| `orders.approve.warehouse` — Level 2 approval in Inbox | no | no | no | yes | no | yes |
| `orders.allocate` — Allocate / Re-allocate button | yes | no | no | yes | no | yes |
| `orders.cancel` — Cancel button on any status | no | no | no | yes | no | yes |
| `picking.view` — Pick Lists index and task detail | no | yes | no | yes | no | yes |
| `picking.assign` — Assign picker to tasks | no | no | no | yes | no | yes |
| `picking.execute` — Desktop pick execution modal | no | yes | no | yes | no | yes |
| `dispatch.view` — Dispatch & Gate page | no | no | no | yes | yes (gate check only) | yes |
| `dispatch.execute` — Confirm Dispatch button | yes | no | no | yes | no | yes |
| `gate.verify` — Gate Check confirm exit | no | no | no | no | yes | yes |
| `pod.upload` — Upload POD modal | yes (optional) | no | no | yes | no | yes |
| `returns.create` — Create Return button on DO | yes | no | no | yes | no | yes |
| `scan.pick` — Pick tile on Scanner Home | no | yes | no | yes | no | yes |
| `scan.dispatch` — Dispatch tile on Scanner Home | yes | no | no | yes | no | yes |
| `scan.gate` — Gate Check tile on Scanner Home | no | no | no | no | yes | yes |

**Negative permission checks — run these explicitly:**

- [ ] A `picker` user cannot see the DO list (navigating to Outbound → Orders shows 403 or redirects)
- [ ] A `clerk` cannot approve DOs — the Approvals Inbox is not visible or shows no actions
- [ ] A `gate-security` user can access Gate Check but cannot see the DO list, dispatch screen, or pick lists
- [ ] A `supervisor` (department-head) can approve their own department's DOs but cannot approve DOs from another department
- [ ] A `clerk` can only submit DOs for their own VW — submitting another VW's DO returns a 403
- [ ] No role except `warehouse-manager` and `system-admin` can cancel an allocated or picking DO

---

## 12. Edge Cases & Stress Tests

### 12.1 Cancel an allocated order — reservations released

- [ ] Allocate a DO fully and confirm reservations exist in `stock_reservations`
- [ ] Note the available qty for the allocated items on the Stock on Hand screen
- [ ] Log in as `warehouse-manager` and cancel the DO (from `allocated` status)
- [ ] Confirm DO status is `cancelled`
- [ ] Available qty on Stock on Hand for the allocated items has returned to the pre-allocation value
- [ ] `delivery_order_allocations` rows show `status = released`
- [ ] No orphan `stock_reservations` rows remain for this DO

### 12.2 Cancel a picking order — staged stock returned

- [ ] Partially execute a pick list so some tasks are `picked` and stock is in staging
- [ ] Log in as `warehouse-manager` and cancel the DO
- [ ] Confirm the system moves staged stock back to source bins via the ledger (a `relocation` ledger entry appears)
- [ ] Stock in the staging location for this DO is 0 after cancellation
- [ ] All remaining reservations are released
- [ ] DO status is `cancelled`

### 12.3 Idempotent dispatch — no double posting

- [ ] Use a script or rapid double-click to submit the dispatch confirmation twice with the same `request_id`
- [ ] Exactly one dispatch record exists in `dispatches` for the DO
- [ ] Exactly one ledger posting of type `dispatch` exists for the staging-to-out movement
- [ ] DO status is `dispatched` (set once, not duplicated)
- [ ] `gate_passes` table has exactly one gate pass for the dispatch

### 12.4 Overdue filter accuracy

- [ ] Create a DO with required date = yesterday, status = `draft` — it appears in the overdue filter
- [ ] Create a DO with required date = tomorrow, status = `draft` — it does NOT appear in the overdue filter
- [ ] Create a DO with required date = yesterday, status = `dispatched` — it does NOT appear in the overdue (outstanding) filter (it was late but is now gone)
- [ ] Create a DO with required date = yesterday, status = `allocated` — it DOES appear in the overdue filter (outstanding and overdue)

### 12.5 Concurrent allocation — two orders, same stock

- [ ] On a MySQL environment, set up two DOs each requesting the last 10 units of the same item
- [ ] Trigger allocation for both DOs simultaneously (or in rapid succession)
- [ ] Only one DO should be fully allocated; the other should be `partially_allocated` or `approved` (with 0 allocation), not `allocated`
- [ ] No stock reservation exceeds actual on-hand qty
- [ ] Run `php artisan wms:stock:verify` — output should be clean (no discrepancies)

### 12.6 Short pick with reallocation — task count increases

- [ ] Set short pick behaviour to `reallocate` in Settings
- [ ] Execute a short pick on a task (e.g., pick 5 out of 10 requested)
- [ ] Verify the system found an alternative bin and added a new task to the pick list for the remaining 5 units
- [ ] The new task appears at the end of the pick list with the alternative location
- [ ] Execute the new task — pick list completes correctly

### 12.7 FEFO minimum shelf life boundary

- [ ] Set minimum shelf life to 14 days
- [ ] Have a batch expiring in exactly 14 days (boundary case)
- [ ] Run allocation with FEFO rule — a batch expiring in exactly 14 days from today should be **excluded** (the check is "expiring within X days", so the boundary is exclusive)
- [ ] Have a batch expiring in 15 days — this should be **included**
- [ ] Verify the batch selection matches the expected behaviour

### 12.8 Backorder chaining

- [ ] Allocate a partially-allocated DO that has a backorder — confirm the original DO proceeds to `picking`
- [ ] Ensure the backorder DO can be independently allocated, picked, and dispatched
- [ ] The original DO's Show page links to the backorder; the backorder's Show page links back to the original

### 12.9 Stock verification after full outbound cycle

- [ ] Complete the full cycle for a DO: draft → submitted → approved → allocated → picking → picked → dispatched → delivered (with POD)
- [ ] Run `php artisan wms:stock:verify`
- [ ] Output must show no discrepancies — all on-hand, allocated, and available figures are internally consistent

### 12.10 Gate pass — exit once only

- [ ] Issue a gate pass and confirm exit via Gate Check
- [ ] Attempt to confirm exit again on the same gate pass (desktop and scanner)
- [ ] Both attempts show "Already exited on [timestamp]" and do not update the `exited_at` field again
- [ ] The gate pass `status` remains `exited` (not a new status or duplicate row)

---

## 13. Dashboard Integration

- [ ] Log in as `warehouse-manager` — the main WMS dashboard shows:
  - [ ] "Today's Dispatches" count and on-time rate (dispatched on or before required date vs. after)
  - [ ] "Orders Awaiting Approval" count (clickable, goes to Approvals Inbox)
  - [ ] "Open Pick Lists" count (clickable, goes to Pick Lists page)
  - [ ] "Overdue Orders" count (clickable, opens Orders list with overdue filter)
- [ ] Recent activity feed on the dashboard shows dispatch and gate exit events from the last 24 hours
- [ ] Clicking a DO number in the activity feed opens the correct DO Show page

---

## 14. Notifications

- [ ] Submit a DO — the approver(s) receive a notification (in-app and/or email)
- [ ] Approve a DO — the requester receives an approval notification
- [ ] Reject a DO — the requester receives a rejection notification with the reason
- [ ] Partial allocation or backorder creation — the requester and warehouse-managers are notified
- [ ] Short pick — warehouse-managers receive a notification with the bin, item, and short qty
- [ ] Dispatch confirmed — the requester and VW users are notified with the delivery note number
- [ ] DO delivered (POD recorded) — the requester is notified
- [ ] Check the in-app notification bell — unread count increments with each event above

---

## 15. Automated Tests (Reference)

Run the following after completing manual testing to confirm automated coverage:

```bash
# SQLite suite — all outbound tests
php artisan test --filter=DeliveryOrder
php artisan test --filter=AllocationService
php artisan test --filter=PickingService
php artisan test --filter=DispatchService
php artisan test --filter=GatePass
php artisan test --filter=ProofOfDelivery

# MySQL concurrency group (requires phpunit.mysql.xml)
php artisan test --group=mysql --configuration phpunit.mysql.xml

# Verify stock integrity
php artisan wms:stock:verify

# Front-end build (no errors)
npm run build
```

Expected: 0 failures on SQLite suite. MySQL group tests for concurrent allocation, concurrent pick, and double dispatch must all pass. `wms:stock:verify` must report clean.
