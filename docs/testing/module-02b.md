# Module 2B — Manual Test Checklist

## Prerequisites
- Run `php artisan migrate` (3 new tables: serial_numbers, stock_movement_serials, data_imports)
- Run `php artisan db:seed --class=RolePermissionSeeder` (adds serials.view and imports.manage)
- Start queue worker: `php artisan queue:work --queue=default`

---

## Part A — Serial Number Tracking

### A1. Opening Balance with Serials

1. Log in as System Admin → Inventory Control → Opening Balance
2. Add a line for a **serial-tracked item** (e.g. a router with `track_serial = true`)
3. Confirm the "Serials" text area appears for that line
4. Enter **one serial per line** (e.g. `RTR-0001`, `RTR-0002`, `RTR-0003`)
5. Observe the live counter: "3 of 3 serials entered" (green when count matches qty)
6. Try posting with **wrong count** (e.g. qty=3, only 2 serials) → expect validation error
7. Try posting with **duplicate serial** in the box → expect validation error
8. Post successfully with matching count → confirm success toast

**Expected**: Balance row created; serial records in `serial_numbers` with status `in_stock`

---

### A2. Serial Lookup Page

1. Navigate to Inventory Control → Serial Lookup
2. Search for a serial number (exact or partial, case-insensitive)
3. Confirm the table shows: Serial No, Item, Status, Location, VW, Batch, First Received, Last Transaction
4. Click the eye icon → history panel slides in showing every movement
5. Use the **Status** and **VW** filters to narrow results
6. Log in as a Department User → confirm they see serials (serials.view is granted to all roles)

---

### A3. Item Detail — Serials Tab

1. Navigate to Master Data → Items → open a serial-tracked item
2. Confirm the **Serials** tab appears (only for serial items)
3. Tab lists all serials with status, location, and VW
4. Confirm non-serial items do NOT show a Serials tab

---

### A4. Stock on Hand — View Serials

1. Inventory Control → Stock on Hand
2. Find a balance row for a serial-tracked item
3. Row action menu → **View Serials** → modal lists all serials in that balance
4. Confirm serial count matches `qty_on_hand`

---

### A5. Movement History — Serials Per Line

1. Inventory Control → Movement History → open a transaction for a serial-tracked item
2. Expand each line → confirms the serials moved in that line (collapsed with count, expand to list)

---

### A6. Reversal with Serials

1. Post an opening balance with 3 serials (e.g. SN-A, SN-B, SN-C)
2. Dispatch 2 serials (SN-A, SN-B) → confirm they become `dispatched`
3. Reverse the dispatch transaction → confirm SN-A and SN-B return to `in_stock` at original location
4. Verify via Serial Lookup that the history timeline shows: received → dispatched → returned

---

### A7. Tracking Flag Guards

1. Try to enable `track_serial` on an item with a weight-based UoM → expect "count-type" error
2. Post opening balance to create stock; then try to disable `track_serial` → expect "cannot change" error

---

## Part B — Data Import

### B1. Download Template

_(Template download not yet connected to a route — implemented in import handler `templateRows()`/`columnInstructions()` — wire up in a future UI pass if needed)_

---

### B2. Upload Flow (for each import type)

#### Departments & VWs (System Admin only)
1. Administration → Data Import → Departments & Virtual Warehouses → **Upload File**
2. Select mode: Create Only
3. Upload a CSV with headers: `department_code, department_name, report_email, vw_code, vw_name`
4. After upload, page redirects to the Import Detail page
5. Wait for validation (auto-refresh every 3 seconds)
6. Confirm "Ready to Import" status with 0 errors
7. Click **Confirm Import** → import runs → "Completed" status with Created/Updated counts

#### Partners
1. Upload CSV with at least one valid and one invalid row (bad email)
2. Validation shows errors with row number and column name
3. Click **Download Error Report** → CSV downloaded with error column
4. Fix the file, re-upload → re-validate → confirm successfully

#### Item Categories
1. Upload CSV; include a child category before its parent → confirm parent is created first
2. `create_or_update` mode: upload same categories again with new names → confirm update

#### Items
1. Upload a valid items CSV (requires: sku, name, category that exists, base_uom that exists)
2. Items with `track_serial=yes` and a weight UoM → validation error "count-type"
3. Items with `track_serial=yes` and a count UoM → passes validation
4. `create_only` mode: re-upload same SKUs → existing items NOT updated

#### Locations
1. Upload with `location_code = MAIN-A-01-02-B` → all parent segments created automatically
2. Verify warehouse hierarchy in Locations page

#### Opening Stock (System Admin + stock.opening-balance permission)
- **With serials sheet**: Upload a two-sheet Excel file. Sheet 1: stock lines. Sheet 2: Serials (sku, location_code, vw_code, batch_no, serial_no).
  - Confirm serial count mismatch is caught at validation time (not halfway through import)
  - Confirm successful import creates STK transactions in chunks of 500 lines
  - Retry the import job manually (simulate by dispatching ConfirmImportJob again) → no double-stock
- **Permissions**: Warehouse Manager cannot upload Opening Stock → 403

---

### B3. Error Report Download

1. Upload a file with 5+ validation errors
2. After failed validation, click **Download Error Report**
3. Open CSV: confirm it has `row`, `column`, `message` columns with correct row numbers

---

### B4. Permission Tests

| Role            | imports.manage | Can upload | Opening Stock |
|-----------------|---------------|------------|---------------|
| System Admin    | ✓             | All types  | ✓             |
| Warehouse Manager | ✓           | All except Departments & Opening Stock | ✗ |
| Department User | ✗             | 403        | 403           |
| Viewer/Auditor  | ✗             | 403        | 403           |

---

## Queue Worker Command

```bash
php artisan queue:work --queue=default --tries=1 --timeout=600
```

Run this in a separate terminal during testing to process validation and import jobs.
