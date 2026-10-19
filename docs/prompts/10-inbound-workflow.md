# Inbound Order Workflow (PO -> GRN)

Read PROJECT-BRIEF.md and docs/BUILD-CONVENTIONS.md first.
Reuse PurchaseOrder, PurchaseOrderWorkflow, GRN, discrepancies, quarantine, putaway, StockLedger, DocumentNumber, notifications, activity log, bulk line upload. Do not rebuild them.
All stock changes through StockLedger. Every post is idempotent.
Step 1: short plan, wait for my confirmation.

## 1. Process flow
Draft -> Submit for approval (department chain + category users) -> Warehouse Manager accepts
-> Manager assigns to Warehouse Supervisor -> Supervisor assigns a Clerk (storekeeper)
-> Clerk receives (GRN auto-created) -> Clerk puts away -> Supervisor validates
-> Completed - Pending Approver Verification -> Approver: Verify & Close / Raise Issue.

## 2. Statuses (PurchaseOrderStatus enum with canTransitionTo(); invalid moves throw)
Draft
Pending Approval (level 1..n, then category level)
Returned for Correction (editable, resubmit restarts from level 1)
Rejected (final, reason required)
Approved - Pending Warehouse Acceptance
Accepted - Pending Assignment
Assigned - Pending Receiving
Receiving in Progress
Partially Received / Discrepancy
Pending Putaway
Putaway in Progress
Pending Supervisor Validation
Completed - Pending Approver Verification
Issue Raised -> Issue Resolved -> back to Completed - Pending Approver Verification
Verified / Closed (final)
Cancelled (only before receiving starts)
Show as a progress timeline on the PO page.

## 3. PO (order creation)
- Header: PO number (sequence), customer PO reference, supplier (partner type supplier), consignee (partner type consignee, separate field), department/VW, warehouse, expected arrival date, currency (default from settings, TZS), source (Supplier Delivery / Customer Return / Warehouse Transfer), package type (Container / Loose cargo), supporting documents (multiple files: pdf/jpg/png, max 10MB each).
- Container package at PO: container number (ISO check), size/type, seal number. No vehicle/transport details on the PO.
- Lines: item, part number (from item, read-only), UoM (dropdown limited to the item's UoMs), qty, unit price, line total (qty x unit price). PO total shown in the header currency.
- No expiry on PO lines. Batch is optional on the line.
- Add part_number to items (Item General tab, searchable, unique per item, optional) if it does not exist.
- Add line + bulk line upload (existing component).
- PO printable: Print / Download PDF with lines, prices, totals, approval history.

## 4. Approval chain (per department) and Approval Centre
- Department settings: ordered levels (1, 2, 3...). Each level has one or more users; any one user at that level can act.
- After department levels: category level (item_category_users for that warehouse). Each category in the lines must be approved by its category users. Skip if no category users exist.
- Actions: Approve, Reject (reason required), Return for Correction (comment required).
- No chain configured: goes straight to Approved - Pending Warehouse Acceptance.
- Store every action in purchase_order_approvals (level, user, action, comment, time).
- New page "Approval Centre": everything waiting for my action (PO, MRN, transfers, adjustments) with Approve / Reject / Return buttons. Remove the old separate "Pending Approval" page and redirect it to the Approval Centre.

## 5. Warehouse acceptance and assignment
- Add role warehouse-supervisor.
- Warehouse-manager: accept (or reject with reason), then assign to a warehouse-supervisor.
- Warehouse-supervisor: assign to a clerk (storekeeper). Reassignment allowed before receiving completes, logged.
- Clerk sees "My assigned receipts". Clicking an order opens receiving directly (no intermediate pending page).
- Only the assigned clerk, their supervisor or the manager can receive and put away this order.

## 6. Receiving (GRN created automatically)
- Clerk records a receiving tally (draft, no stock posted):
    - Receiving dock (select a free receiving_dock location; mark it busy until receiving completes).
    - Vehicle/transport: vehicle type (dropdown from a vehicle_types setting list), plate number, driver name, phone, licence, transporter.
    - Container: arrival, seal check (match / mismatch with reason), delivery partial or full.
    - Per line: actual qty, condition (good / damaged), batch, expiry (only if item tracks expiry), serials (scan/type or "serials missing"), discrepancy reason for short/excess/damaged.
    - Batch: if the item tracks batches and no batch is entered, the system generates one (e.g. B-{yyyymmdd}-{seq}).
    - Date and time recorded automatically.
- "Complete receiving" in one DB::transaction, idempotency key po:{id}:receipt:{n}:
    - Create the GRN. Number inherits the PO: GRN-{PO number}-{n} (n = delivery number).
    - Post good stock via StockLedger. Damaged qty goes to quarantine.
    - Record discrepancies. Excess above the item variance threshold needs warehouse-manager authorisation before posting.
    - Create putaway tasks. Release the dock.
- GRN statuses: Draft -> Received -> Putaway Complete.
- GRN content: GRN number, PO reference, supplier, consignee, department, dock, vehicle/transport details, container details (partial/full, seal), date and time, lines (expected, received, damaged, batch, expiry, serials, bins), discrepancies, clerk, supervisor. Print / Download PDF.
- Partial delivery: each delivery creates its own GRN on the same PO. No approval or verification per partial delivery. PO stays Partially Received until all lines are received or the manager uses "Close short" (reason required).

## 7. Putaway
- Same clerk. No location suggestion; user selects/scans the bin (bin rules apply).
- Printable put-away list (View / Print): item, part number, qty, batch, rule, target bin.
- When all GRNs are put away and no lines remain open (or closed short): status -> Pending Supervisor Validation.
- Supervisor validates (or sends back to the clerk with a comment).
- After validation: status -> Completed - Pending Approver Verification, generate the receiving report PDF (all GRNs of the PO: expected vs actual, discrepancies, condition, batches/serials, bins, clerk, supervisor, dates), notify all approvers.

## 8. Approver verification
- Notification (in-app + email): PO reference, expected vs actual, discrepancies, clerk, completion date, bins, link to the receiving report.
- Any notified approver can: Verify & Close -> Verified / Closed, or Raise Issue (reason + optional attachment) -> Issue Raised.
- No auto-close. Reminder every 2 days until verified (setting).

## 9. Exception resolution
- Short delivery: recorded on GRN; wait for next delivery or manager "Close short".
- Excess: needs warehouse-manager authorisation (accept into stock or reject/return to supplier with reason).
- Damaged: posted to quarantine; later released or written off through existing quarantine flow.
- Seal mismatch: recorded with reason; manager notified; receiving continues only after manager acknowledgement.
- Issue raised by approver: warehouse-manager and clerk notified; manager records resolution as explanation only, or a Stock Adjustment (normal adjustment approval) linked to the issue; then Issue Resolved, approvers notified to verify again. Multiple issues allowed; history kept in po_issues.

## 10. Role-permission matrix (enforce in policies and services, not only UI)
| Action | department-user | approver (chain/category) | warehouse-manager | warehouse-supervisor | storekeeper (clerk) | system-admin | viewer-auditor / data-analyst |
| Create / edit draft / submit / cancel | yes (own) | no | no | no | no | yes | no |
| Approve / reject / return | no | yes (own level) | no | no | no | yes | no |
| Accept / reject order | no | no | yes | no | no | yes | no |
| Assign supervisor | no | no | yes | no | no | yes | no |
| Assign clerk | no | no | yes | yes | no | yes | no |
| Receive / put away | no | no | yes | yes | yes (assigned only) | yes | no |
| Authorise excess / close short | no | no | yes | no | no | yes | no |
| Validate completion | no | no | yes | yes | no | yes | no |
| Verify & close / raise issue | no | yes (notified approvers) | no | no | no | yes | no |
| Resolve issue | no | no | yes | no | no | yes | no |
| View | own | assigned | all | assigned warehouse | assigned | all | all (read only) |
Print the final matrix in the report.

## 11. Notifications (in-app + email, one per event per order, no duplicates on retry)
Submitted / next level -> approvers of that level. Returned / Rejected -> creator. Final approval -> warehouse-managers. Accepted / rejected by warehouse -> creator. Assigned -> supervisor, then clerk. Seal mismatch / excess -> warehouse-manager. Partial receipt -> creator (info). Pending validation -> supervisor. Completed -> all approvers. Issue raised -> warehouse-manager + clerk. Issue resolved -> all approvers. Verified / Closed -> creator, clerk, supervisor.

## 12. Screens
- PO page: timeline, lines with prices, approval history, assignment, GRNs, receiving report, issues, actions per role, Print.
- Department approval chain settings. Vehicle types list in settings.
- Approval Centre. Pending acceptance and assignment (manager). My assigned receipts (clerk). Pending validation (supervisor).
- Dashboard KPI cards for these queues.

## 13. Tests (filter InboundWorkflow)
- Chain order, return restarts at level 1, reject ends, category level requires all categories, no chain skips to warehouse
- Manager -> supervisor -> clerk assignment; only assigned clerk can receive (others 403)
- UoM limited to item UoMs; line total and PO total correct
- Complete receiving creates GRN-{PO}-{n} and posts stock once (retry does not double post)
- Auto batch generated when missing; dock busy then released
- Damaged to quarantine, excess needs authorisation, short recorded, seal mismatch needs acknowledgement
- Partial delivery: second GRN, no verification until complete or closed short
- Supervisor validation required before approver notification
- Verify closes; raise issue -> resolve -> re-verify; invalid status moves rejected

## Deliverable
Run only filtered tests. Short report: files changed, role matrix, test results.
