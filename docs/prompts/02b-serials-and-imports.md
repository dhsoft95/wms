# Module 2B: Serial Number Tracking + Excel Data Import

## Context
- Laravel + Livewire WMS. Module 1 (Foundation) and Module 2 (Stock Ledger) are done and tested.
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` sections 0.1 (track serial), 5.3 (traceability) and 10.3 (data migration).
- Reason: the first client handles telecom equipment (routers, phones, radios) that must be tracked one by one by serial number, and has too many items and stock lines to type in by hand at go-live.
- All stock changes still go only through `StockLedger`. Imports must never write stock tables directly.
- Use existing UI components, modals, row-action menu, table styles.

## Step 1 - Inspect and plan first
Propose tables, DTO changes, ledger changes, import architecture and package choice. Wait for my confirmation.

---

# Part A - Serial number tracking

## A1. Tables
- `serial_numbers`: `item_id`, `serial_no`, `batch_id` (nullable), current position (`location_id`, `virtual_warehouse_id`, `lpn_id` nullable, `stock_status`), `status` (`in_stock`, `dispatched`, `written_off`), `first_received_at`, `last_transaction_id`, timestamps.
    - Unique (`item_id`, `serial_no`). Index on `serial_no` alone for global search.
- `stock_movement_serials`: `stock_movement_id`, `serial_number_id`. Records exactly which serials each ledger line moved.

## A2. Ledger changes
- `StockLine` gets `serials: string[]` (optional). All helpers (`receive`, `move`, `changeOwner`, `changeStatus`, `adjust`, `openingBalance`) accept serials.
- Rules for items with `track_serial = true`:
    - Every line must list serials, and the count must equal the line quantity in base UoM (2 CTN of a 20-PCS carton = exactly 40 serials).
    - Base UoM must be a count type (PCS). Reject enabling `track_serial` on weight, volume or length items.
    - No duplicate serial within one posting.
    - Inbound (`+`): serial must not already be `in_stock`. A serial with status `dispatched` or `written_off` can come back (returns); it keeps its history.
    - Outbound or move (`−`): every serial must currently sit at the source stock key (same item, location, VW, status, batch, LPN).
    - Serial position and status update in the same DB transaction as the balance.
- Rules for items without `track_serial`: serials must be empty; reject if provided.
- `reverse()` restores serials to their previous position and status.
- `wms:stock:verify` also checks: for serial items, count of `in_stock` serials at each stock key = balance `qty_on_hand`.
- Use bulk inserts for serial rows; a posting with 5,000 serials must complete in a few seconds on MySQL.
- `track_serial` cannot be changed once the item has stock (extend the existing guard).

## A3. Screens
- **Opening Balance form**: for serial items, show a serials box (paste or scan, one per line). Live counter "38 of 40 serials entered". Highlight duplicates and serials already in stock before posting.
- **Serial Lookup page** (new): search by serial number → item, current location, VW, status, batch, and a timeline of every movement (date, type, reference number, from/to location, user).
- **Item detail, Serials tab**: list of serials with status and location, searchable.
- **Stock on Hand**: for serial items, row action "View serials" opens a modal listing serials in that balance.
- **Movement History**: transaction detail shows serials per line (collapsed list with count).
- All VW-scoped: a department user only finds serials in their own VW.

## A4. Permissions
`serials.view` for System Admin, Warehouse Manager, Storekeeper, Viewer/Auditor, Department User (own VW). Register the Serial Lookup page in `config/navigation.php`.

---

# Part B - Excel data import

## B1. Package and infrastructure
- Use `spatie/simple-excel` (streaming, handles large files) for reading and writing `.xlsx` and `.csv`.
- Use the `database` queue. Validation and import run as queued jobs with progress, so large files don't time out. Tell me the queue worker command to run.
- Table `data_imports`: `type`, `original_filename`, `stored_path`, `status` (`uploaded`, `validating`, `validated`, `failed_validation`, `importing`, `completed`, `failed`), `mode` (`create_only`, `create_or_update`), `total_rows`, `valid_rows`, `error_rows`, `created_count`, `updated_count`, `errors` (json, row number + column + message), `summary` (json), `uploaded_by`, `confirmed_by`, timestamps.
- Files stored in `storage/app/imports`, not public.

## B2. Flow (spec 10.3) for every import type
1. **Download template**: `.xlsx` with the exact headers, one example row, and an "Instructions" sheet explaining each column, allowed values and which are required.
2. **Upload**: file type and size check (max 20 MB).
3. **Validate** (queued): check every row and show a progress bar. Nothing is saved yet.
4. **Review**: summary cards ("Will create 420, update 35, errors 12"), preview of the first 50 valid rows, and an error table (row, column, message).
5. **Download error report**: the original file with an added "Errors" column, so the user can fix it and re-upload.
6. **Confirm** (only if 0 errors): runs the import in a queued job.
7. **Result**: counts, link to the created records, and for stock imports the STK transaction numbers.
- Records are matched by code (SKU, location code, partner code, VW code, UoM code, category name). Imports never delete anything.
- Every import is logged in the activity log.

## B3. Import types and columns
1. **Departments & Virtual Warehouses**: department_code, department_name, report_email, vw_code, vw_name.
2. **Partners**: code, name, types (comma-separated), tin, vrn, contact_person, phone, email, address, city, country.
3. **Item categories**: name, parent_name.
4. **Items**: sku, name, description, category (created if missing, when allowed by a checkbox), base_uom, length_cm, width_cm, height_cm, weight_kg, track_batch, track_expiry, track_serial, min_stock, max_stock, default_vw_code, barcode, barcode_2, uom_2, factor_2, uom_3, factor_3.
5. **Locations**: warehouse_code, full location code (e.g. `DAR-A-03-02-B`) OR parent_code + level + segment_code, type, max_pallets, max_weight_kg. Parents are created in the right order automatically when the full code is used.
6. **Opening stock** (System Admin only, needs `stock.opening-balance`): sku, location_code, vw_code, qty, uom, batch_no, expiry_date, status (`available` or `on_hold`).
    - Second sheet **"Serials"**: sku, location_code, vw_code, batch_no, serial_no (one row per serial). Serial counts must match the quantities on the first sheet for the same sku/location/vw/batch.
    - Posts through `StockLedger::openingBalance()` in chunks of 500 lines; each chunk is its own STK transaction with idempotency key `import:{id}:chunk:{n}`, so a retried job never double-posts.
    - All ledger rules apply (blocked location, missing expiry, duplicate serial, etc.) and are reported at validation time, not halfway through the import.

## B4. Screen
- **Data Import** page under Administration: one card per import type (description, Download template, Upload), plus an import history table (type, file, status, counts, user, date, row actions: View, Download errors).
- Import detail page shows the review step, progress and result.

## B5. Permissions
`imports.manage` for System Admin (all types) and Warehouse Manager (all except Opening stock and Departments). Opening stock also requires `stock.opening-balance`.

---

## Tests (Pest)
**Serials**
- Serial count must equal base qty; duplicate in posting rejected; serial already in stock rejected on inbound.
- Outbound/move only with serials at the source key; serials move with the stock; partial move of 3 of 10 serials.
- Dispatched serial can be received again; history kept.
- Reversal restores serial positions.
- Non-serial item rejects serials; `track_serial` on non-count UoM rejected; flag locked once stock exists.
- `wms:stock:verify` detects a serial/balance mismatch.
- VW scope on Serial Lookup.

**Imports**
- Each type: valid file imports correctly; `create_only` skips existing; `create_or_update` updates.
- Validation errors reported with correct row and column; confirm blocked while errors exist.
- Opening stock: chunked posting, idempotent retry (run the job twice → no double stock), serial sheet count mismatch reported, all ledger rules caught at validation.
- 10,000-row items file and 5,000-serial stock file import successfully (mark as slow test group `slow`).
- Permissions per role.

Run `php artisan test` (0 failures) and the `mysql` group. `npm run build` clean.

## Manual checklist
Write `docs/testing/module-02b.md` covering: serial opening balance, serial lookup, serial in Stock on Hand and Movement History, reversal with serials, each import type end to end, error report download and re-upload.

## Deliverable
Tables, ledger changes, import architecture, queue command, permissions, files changed, test results, decisions taken.

Stop when done. Do not start Module 3.
