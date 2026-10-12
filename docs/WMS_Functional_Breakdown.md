# Warehouse Management System (WMS)

## Detailed Functional Breakdown

Below is the detailed functional breakdown: for each module, what it's for, what data it holds, how the workflow runs step by step, the business rules, and the screens it needs.

## 0. Foundation Module

Every other module depends on this one, so it gets built first.

### 0.1 Item Master (SKU)

**Purpose:** One record per product the warehouse handles. Every receipt, movement and dispatch points to an item.

**Fields:**

| Field            | Notes                                       |
|----------------------|-------------------------------------------------|
| SKU code             | Unique, system or manual                        |
| Name / description   |                                                 |
| Category             | For filtering and reporting                     |
| Base UoM             | Piece, kg, litre, etc.                          |
| Pack UoM conversions | e.g. 1 carton = 24 pcs, 1 pallet = 40 cartons   |
| Dimensions / weight  | Used for capacity checks                        |
| Track batch          | Yes/No                                          |
| Track expiry         | Yes/No (forces FEFO picking)                    |
| Track serial         | Yes/No (optional)                               |
| Barcode(s)           | Can have multiple (supplier barcode + internal) |
| Min / max stock      | For low-stock alerts                            |
| Owning department    | Default virtual warehouse                       |
| Status               | Active / Inactive                               |

**Rules:**

- An item with stock or transactions cannot be deleted, only deactivated.

- If "track batch" is on, every receipt must capture a batch number.

- If "track expiry" is on, expiry date is mandatory at receipt.

### 0.2 Warehouse Layout (Locations)

**Purpose:** Models the physical warehouse so the system knows exactly where every unit is.

**Hierarchy:** Warehouse → Zone → Aisle → Rack → Level → Bin

Example location code: ZNZ-A-03-02-B (Zanzibar, Zone A, Aisle 3, Level 2, Bin B).

**Location types:**

- **Container yard / parking slot:** where containers sit before unloading

- **Receiving dock:** temporary holding while goods are being checked

- **Storage:** normal racked or floor storage

- **Staging / dispatch bay:** picked goods waiting for loading

- **Quarantine:** damaged, disputed, or held goods (not available for picking)

**Fields:** code, type, parent location, max capacity (pallets/weight), status (Active, Blocked, Full), barcode.

**Rules:**

- Goods cannot be moved into a Blocked location.

- Stock in Quarantine is never counted as available.

- A location with stock cannot be deleted.

### 0.3 Partners

Suppliers/shippers and consignees/customers: name, contact, address, TIN, type. Used on containers, GRNs and delivery orders.

### 0.4 Departments

Name, code, head of department, email for reports. Each department maps to one or more virtual warehouses (Module 6).

### 0.5 Users, Roles and Permissions

| Role          | Typical access                                     |
|-------------------|--------------------------------------------------------|
| System Admin      | Everything, including settings and users               |
| Warehouse Manager | All operations across all departments, approvals       |
| Department User   | Create requests and view stock for own department only |
| Storekeeper       | Receiving, putaway, picking, dispatch execution        |
| Scanner Operator  | Mobile scanning screens only                           |
| Viewer / Auditor  | Read-only, reports and traceability                    |

**Rules:** Permissions are granular (view, create, edit, approve, delete per module). Every user is assigned to one or more virtual warehouses, which filters all data they see.

## 1. Container Management

**Purpose:** Track every container from the moment it's expected until it's emptied and released, and use its packing list as the "expected goods" for receiving.

#### Data captured

| Field                 | Notes                                           |
|---------------------------|-----------------------------------------------------|
| Container number          | Unique (ISO format check, e.g. MSKU1234567)         |
| Seal number               | Verified on arrival                                 |
| Size / type               | 20ft, 40ft, 40HC, reefer                            |
| Bill of Lading no.        |                                                     |
| Vessel / voyage           |                                                     |
| Shipper / consignee       | From Partners                                       |
| ETA / actual arrival date |                                                     |
| Parking slot              | Yard location                                       |
| Owning department(s)      | A container can carry goods for several departments |
| Attachments               | BL, packing list, photos, inspection report         |

#### Packing list import

The client sends the packing list (usually Excel). The system:

1.  Uploads the file and maps columns (SKU, description, qty, UoM, batch, weight, department).

2.  Validates each row: unknown SKUs are flagged (option to create them or map to existing ones).

3.  Saves the lines as **expected lines** on the container.

#### Workflow

1.  **Expected:** Container created before arrival with packing list attached.

2.  **Arrived:** Gate staff confirm arrival, record actual date/time, verify seal number. If the seal doesn't match, a discrepancy is logged and the manager is notified.

3.  **Parked:** Assigned to a yard slot. Moves between slots are recorded with history.

4.  **Unloading:** Storekeeper opens the container for receiving. This automatically creates a GRN in Module 2, pre-filled with the expected lines.

5.  **Unloaded:** All goods counted. System compares actual vs expected.

6.  **Released / Returned:** Empty container returned to shipping line, return date recorded.

#### Business rules

- Container number must be unique among active containers.

- A container can't move to Unloading without being Arrived.

- Can't be marked Unloaded while its GRN still has unconfirmed lines.

- Discrepancies (short, excess, damaged) are recorded per line with reason and photos.

#### Screens

- Container list (filter by status, department, dwell days)

- Container detail (info, expected lines, received lines, variance, documents, status history)

- Yard map / parking view showing which slot holds which container

- Dwell time dashboard (containers parked more than X days highlighted, since demurrage costs money)

## 2. Inbound: Receiving and Putaway

**Purpose:** Turn arriving goods into confirmed, located stock.

### 2.1 Goods Received Note (GRN)

**Sources:**

- From a container (auto-created, expected lines pre-filled)

- Manual receipt (local supplier delivery, returns)

**GRN header:** GRN number, date, source (container/supplier), department/virtual warehouse, receiving dock, received by.

**GRN line:** item, expected qty, received qty, UoM, batch, expiry, condition (Good / Damaged), variance, remarks.

#### Receiving workflow

1.  Storekeeper opens the GRN on desktop or scanner.

2.  Scans each item barcode (or pallet label). The system matches it to an expected line.

3.  Enters or confirms quantity, batch and expiry.

4.  Marks condition. Damaged goods go to Quarantine instead of normal stock.

5.  System calculates variance per line: **Short** (received \< expected), **Excess** (received \> expected), **Unexpected item** (scanned item not on the packing list).

6.  Variances require a reason code and, above a configurable threshold, manager approval.

7.  Storekeeper prints labels for pallets/cartons (Module 7). Each pallet gets a unique License Plate Number (LPN).

8.  GRN is confirmed. Stock is created in the **receiving dock** location, status "Received, not put away".

### 2.2 Putaway

**Purpose:** Move received goods from the dock to their storage location.

1.  System creates putaway tasks for each received pallet/line.

2.  Location suggestion (configurable): same item's existing location (consolidation), empty location in the item's zone, or manual choice.

3.  Operator scans the pallet LPN, moves it, scans the destination bin.

4.  System validates: location active, has capacity, belongs to the right virtual warehouse.

5.  Stock moves from dock to bin and becomes **Available**.

#### Statuses

Draft → Receiving → Received → Putaway in Progress → Putaway Complete → Closed

#### Business rules

- Stock is not available for picking until putaway is confirmed.

- Confirmed GRNs cannot be edited. Corrections are made through a stock adjustment (Module 4), keeping the audit trail clean.

- Each confirmed GRN writes movement records to the ledger (Module 5).

#### Screens

- GRN list and detail

- Receiving screen (scan-driven, mobile)

- Variance review and approval screen

- Putaway task list and putaway scan screen

## 3. Outbound: Picking and Dispatch

**Purpose:** Get goods out accurately, from the right batch, location and department.

### 3.1 Delivery Order (DO)

**Header:** DO number, requesting department, consignee/destination, required date, priority, delivery address, remarks.

**Lines:** item, requested qty, UoM, specific batch (optional).

#### Workflow

1.  **Draft:** Department user creates the request.

2.  **Approved:** Department head or warehouse manager approves. (Approval levels configurable.)

3.  **Allocated:** System reserves stock using the allocation rule: FIFO (oldest received first), FEFO (earliest expiry first, for expiry-tracked items), or specific batch if requested. If stock is insufficient, the system offers partial allocation or backorder.

4.  **Picking:** System generates pick tasks grouped and sorted by location route so the picker walks efficiently.

5.  Picker scans location → scans item/LPN → confirms qty. Wrong item or wrong batch gets rejected at scan.

6.  **Short pick:** If physical stock is less than system stock, picker records a short pick. This triggers a count task for that location (system stock is probably wrong).

7.  **Picked:** Goods moved to the dispatch bay / staging.

8.  **Dispatched:** Loader confirms loading. Captures vehicle number, driver name, driver phone, seal (if containerised), dispatch time. Delivery Note prints.

9.  **Delivered (optional):** Proof of delivery uploaded (signed delivery note photo).

#### Business rules

- Allocation reduces **available** qty immediately, even before picking, so two orders can't take the same stock.

- Cancelling an allocated order releases the reservation.

- A department can only request stock from its own virtual warehouse.

- Dispatched orders cannot be edited; reversals go through a return GRN.

#### Screens

- DO list and detail

- Approval queue

- Allocation view (which batch/location is reserved)

- Pick task screen (mobile)

- Dispatch confirmation screen

- Printable Delivery Note / Goods Delivery Note

## 4. Inventory and Stock Movements

**Purpose:** Handle everything that changes stock other than receiving and dispatch.

### 4.1 Stock quantities tracked

For every combination of item + batch + location + virtual warehouse:

| Quantity         | Meaning                   |
|----------------------|-------------------------------|
| On hand              | Physically present            |
| Allocated / reserved | Promised to a delivery order  |
| On hold              | Quarantine or blocked         |
| Available            | On hand − allocated − on hold |

### 4.2 Internal transfer / relocation

Move stock from one bin to another (reorganising, consolidating). Scan source bin → scan item/LPN → enter qty → scan destination bin. No change to ownership.

### 4.3 Inter-department transfer

Move stock ownership from one virtual warehouse to another (e.g. Department A lends materials to Department B).

1.  Requesting department creates transfer request.

2.  Owning department approves.

3.  Stock ownership changes (physical location may stay the same).

4.  Both departments see the movement in their history.

### 4.4 Stock adjustment

Correct stock for: damage, loss, found stock, expiry write-off, counting errors.

- Reason code mandatory.

- Approval required (thresholds configurable by qty or value).

- Positive and negative adjustments both logged.

### 4.5 Quarantine / hold

Put stock on hold (suspected damage, quality check, dispute). Held stock can't be allocated. Release returns it to available; rejection moves it to write-off via adjustment.

### 4.6 Cycle count / stock take

1.  Manager creates a count: full warehouse, by zone, by item, or random selection.

2.  Option to **freeze** locations during the count (no movements allowed).

3.  Counters scan locations and items, enter quantities. Optionally "blind count" (counter doesn't see the system qty, which prevents just copying the number).

4.  System compares counted vs system qty.

5.  Manager reviews variances, may request recount.

6.  On approval, variances post as stock adjustments automatically.

#### Screens

- Stock on hand inquiry (by item, location, batch, department)

- Transfer screens (desktop + mobile)

- Adjustment request and approval

- Quarantine list

- Count plan, count entry (mobile), variance review

## 5. Physical Tracking and Traceability

**Purpose:** This is the "memory" of the system. The SOW's core requirement is reconciling receipts against dispatches, and this module makes that possible.

### 5.1 Stock movement ledger

Every transaction in Modules 1 to 4 writes a movement record. Records are **never edited or deleted**.

| Field                   | Example                                                   |
|-----------------------------|---------------------------------------------------------------|
| Movement type               | Receipt, Putaway, Pick, Dispatch, Transfer, Adjustment, Count |
| Item, batch, LPN            |                                                               |
| Qty (+/−)                   |                                                               |
| From location / to location |                                                               |
| From / to virtual warehouse |                                                               |
| Reference document          | GRN-0042, DO-0108, ADJ-0007                                   |
| User                        | Who did it                                                    |
| Timestamp                   | When                                                          |
| Device                      | Desktop or scanner ID                                         |

Current stock on hand is always the sum of the ledger, which means stock figures can always be proven.

### 5.2 Receipt vs dispatch reconciliation

For any container or GRN, one screen shows:

- Total received

- Currently in stock (and where)

- Dispatched (to which DOs, which dates)

- Adjusted / written off

- Balance check: received = in stock + dispatched + adjusted (any mismatch highlighted)

### 5.3 Traceability search

Search by container number, GRN, batch, LPN, or SKU and get the full timeline: arrived in container X, received on GRN Y, put in bin Z, moved to bin W, picked on DO V, dispatched on truck T123ABC on date D.

Useful for recalls, audits, and customer disputes ("where did my goods go?").

### 5.4 Audit log

Separate from stock movements: records who created, edited, approved, cancelled or deleted any document or master record, with old and new values.

## 6. Virtual Warehouses (Departmental Segregation)

**Purpose:** One physical warehouse, but each department behaves as if it has its own.

#### How it works

- Each virtual warehouse (VW) is linked to a department.

- Every stock record carries a VW owner. The same bin can hold stock from different VWs, and the system keeps them separate.

- Every document (GRN, DO, transfer, adjustment) belongs to a VW.

#### Access rules

- Department users see only their VW's stock, documents and reports. This is enforced at query level (global scope), not just hidden in the UI.

- Department users can create requests but execution (picking, putaway) is done by warehouse staff.

- Warehouse manager and admin see all VWs.

#### Department self-service

- Dashboard: stock value/qty, pending inbound, pending outbound, low stock alerts, expiring items

- Stock inquiry for own materials

- Request delivery orders

- View own history and reports

#### Rules

- Stock can't move between VWs except through an approved inter-department transfer.

- A container's packing list lines can be assigned to different VWs, so one container can feed several departments.

## 7. Barcode Label Generation

**Purpose:** Print identification labels so every pallet, item and location can be scanned.

### 7.1 Label templates

Admin designs templates without code:

- **Size:** width × height in mm (e.g. 100×150 shipping, 50×25 item, 100×50 location)

- **Fields:** pick from available data (SKU, description, batch, expiry, qty, UoM, LPN, container no., GRN no., department, location code, receive date, company logo)

- **Layout:** position each field (x/y), font size, bold, alignment

- **Barcode:** type (Code128, QR, EAN-13, DataMatrix), which field it encodes, size and position

- Preview before saving

### 7.2 Label types

| Label      | Printed when       | Barcode encodes |
|----------------|------------------------|---------------------|
| Pallet / LPN   | Receiving              | Unique LPN          |
| Item / carton  | Receiving or on demand | SKU (+ batch)       |
| Location / bin | Setup                  | Location code       |
| Container      | Arrival                | Container number    |
| Dispatch       | Dispatch               | DO number           |

### 7.3 Printing

- Print from the relevant document (GRN prints its pallet labels in one click)

- Bulk printing (e.g. all bin labels for a zone)

- Output as PDF (any printer) or ZPL (direct to Zebra thermal printers)

- Reprints logged with reason, to prevent duplicate labels in circulation

## 8. Barcode Scanning

**Purpose:** Make every warehouse transaction fast and error-proof by scanning instead of typing.

#### Devices

- Handheld scanners with keyboard wedge (scan acts like typing into the field)

- Android rugged devices (Zebra, Honeywell) running the web app

- Phone camera scanning as fallback

#### Scan workflows

| Workflow | Scan sequence                        |
|--------------|------------------------------------------|
| Receive      | GRN → item/pallet → confirm qty          |
| Putaway      | LPN → destination bin                    |
| Pick         | Bin → item/LPN → confirm qty             |
| Dispatch     | DO → each LPN loaded                     |
| Transfer     | Source bin → item/LPN → destination bin  |
| Count        | Bin → each item → qty                    |
| Lookup       | Any barcode → shows what it is and where |

#### Validation on every scan

- Unknown barcode → error

- Wrong item for this task → error

- Wrong location → error

- Item belongs to another department → error

- Quantity exceeds expected/allocated → warning or block

- Clear green/red screen feedback with sound, so operators don't need to read the screen closely

#### Design notes

- Large buttons, minimal typing, works in portrait on small screens

- The barcode resolver should recognise what was scanned from its pattern/prefix (LPN, bin, SKU, container) so one input field handles everything

## 9. Automated Reports

**Purpose:** Each department gets its weekly reports without anyone asking.

### 9.1 Required weekly reports (per department)

**Stock on Hand**

- As at report date

- Columns: SKU, description, batch, expiry, location, qty on hand, allocated, available, UoM

- Grouping by category; totals; items below minimum highlighted

**Goods Received**

- For the week

- Columns: date, GRN no., container no., supplier, SKU, qty expected, qty received, variance, condition

**Goods Delivered**

- For the week

- Columns: date, DO no., consignee, SKU, batch, qty dispatched, vehicle, driver

### 9.2 Scheduling and distribution

- Admin configures per report: day of week, time, departments, recipient emails (To/CC)

- Formats: PDF (for reading) and Excel (for working)

- Runs automatically via scheduled job; sends email with attachments

- Delivery log: sent/failed per recipient, with resend option

### 9.3 On-demand reports

Same three reports runnable any time with filters (date range, department, item, container), plus recommended extras:

- Container dwell time

- Stock movement history

- Adjustments and write-offs

- Expiry / aging report

- Receipt vs dispatch reconciliation

- User activity report

## 10. Integrations and Data

**Purpose:** Connect the WMS with the client's other systems and load initial data.

### 10.1 API

- REST API with token authentication

- Endpoints: items, stock levels, create DO, create expected receipt (ASN/container), GRN and dispatch confirmations

- Rate limiting, request logging

### 10.2 Outbound events (webhooks)

Notify external systems when a GRN is confirmed, a DO is dispatched, or stock changes, so their ERP stays in sync.

### 10.3 Data migration

Excel import templates for:

- Items/SKUs

- Departments and virtual warehouses

- Locations

- Partners

- Active containers

- Opening stock balances (item, batch, location, VW, qty)

Each import: upload → validate → error report per row → preview → confirm. Opening stock posts as a special "Opening Balance" movement so the ledger is complete from day one.

### 10.4 Sync monitoring

Integration log showing each message sent/received, status, error details, and a retry button for failures.

### 10.5 Export

Every list and report exportable to Excel/CSV for audits.

## 11. Platform and General

- **Web-based:** responsive, works on desktop, tablet and handheld; HTTPS; accessible from anywhere with internet

- **Dashboard:** containers in yard, today's receipts and dispatches, pending approvals, low stock, expiring stock

- **Notifications:** in-app and email for container arrival, approvals needed, low stock, expiry, failed integrations

- **Settings:** company profile, document number sequences (GRN-2026-0001), reason codes, allocation rule, approval thresholds

- **Security:** role-based access, password policy, session timeout, login history

- **Backups:** automated daily database backups

## Suggested Build Order

1.  Foundation (items, locations, departments, users/roles, virtual warehouses)

2.  Stock ledger and stock-on-hand engine

3.  Container management + receiving + putaway

4.  Labels + scanning

5.  Picking + dispatch

6.  Transfers, adjustments, counts

7.  Traceability and reconciliation screens

8.  Reports and scheduling

9.  Integrations and migration tools

The ledger (step 2) is the most important architectural piece: if every module writes movements through one service, stock accuracy, traceability and the receipt-vs-dispatch reconciliation all come for free.
