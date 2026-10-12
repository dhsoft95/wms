# Module 2: Stock Ledger and Stock-on-Hand Engine

## Context
- Laravel + Livewire WMS. Module 1 (Foundation) is done: items, UoMs, `UomConverter`, warehouses, locations, partners, settings, `DocumentNumber`, VWs and `BelongsToVirtualWarehouse`.
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` sections 4.1 (stock quantities) and 5.1 (stock movement ledger). Read the "Suggested Build Order" note: every module writes movements through one service.
- This module builds the engine plus a few screens to see and test stock. It does NOT build receiving, picking, transfers, adjustments or counts (later modules). Those will only call this engine.
- Use the existing UI components, row-action menu, table styles and modal pattern.

## Step 1 - Inspect and plan first
1. Confirm the empty `App\Services\Inventory\StockLedger` placeholder and the skipped arch test exist.
2. Propose tables, models, DTOs, the public service API and the list of movement types.
3. Wait for my confirmation before writing code.

## Step 2 - Core concepts
- **Stock key** (one balance row per unique combination): `item_id` + `batch_id` (nullable) + `lpn_id` (nullable) + `location_id` + `virtual_warehouse_id` + `stock_status`.
- **Stock status** enum: `available`, `on_hold`. Stock in a `quarantine` location is always `on_hold`.
- **Quantities** (spec 4.1), all in base UoM, `decimal(18,4)`:
    - On hand = sum of ledger for that key
    - Allocated = sum of active reservations on that key
    - On hold = on hand where status is `on_hold`
    - Available = on hand (status `available`) − allocated
- **Allocation is not a physical movement.** Reservations live in their own table and never write ledger rows.

## Step 3 - Tables
- `batches`: `item_id`, `batch_no`, `expiry_date` (nullable), `manufactured_date` (nullable), timestamps. Unique (`item_id`, `batch_no`).
- `lpns` (license plates, minimal for now): `code` (unique, from `DocumentNumber` type LPN), `status` (`active`, `consumed`), timestamps. Full LPN handling comes in Modules 4 and 5.
- `stock_transactions` (header, one per posting): `number` (from a new document sequence type `STK`), `type` (movement type enum), `reference_type`, `reference_id`, `reference_no` (e.g. GRN-2026-0001), `idempotency_key` (unique, nullable), `notes`, `posted_by`, `device` (`desktop` or scanner ID), `posted_at`, `reversed_by_transaction_id` (nullable), `reverses_transaction_id` (nullable). No `updated_at`.
- `stock_movements` (ledger lines): `stock_transaction_id`, `item_id`, `batch_id`, `lpn_id`, `location_id`, `virtual_warehouse_id`, `stock_status`, `qty` (signed, base UoM), `entered_qty`, `entered_uom_id`, `created_at`. No `updated_at`. Indexed on every key column and `created_at`.
- `stock_balances`: the stock key columns + `qty_on_hand`, `qty_allocated`, `updated_at`. Unique index on the full stock key (handle NULLs so uniqueness works on MySQL, e.g. store 0 instead of NULL for no batch/LPN, or a generated hash column `key_hash`).
- `stock_reservations`: `stock_balance_id`, `qty`, `reference_type`, `reference_id`, `status` (`active`, `released`, `consumed`), timestamps.

## Step 4 - Movement types (enum)
`opening_balance`, `receipt`, `putaway`, `relocation`, `pick`, `dispatch`, `ownership_transfer`, `adjustment`, `count_adjustment`, `hold`, `release`, `reversal`.
Each type documents which directions are allowed (in only, out only, or paired in/out).

## Step 5 - `StockLedger` service (the only writer)
Public API (DTO-based, no arrays of loose values):
- `post(StockPosting $posting): StockTransaction`, the single generic entry point. A posting has a type, a reference, an optional idempotency key, and one or more lines.
- Thin helpers built on `post()`: `receive()`, `move()` (location to location, same owner), `changeOwner()` (VW to VW, same location), `changeStatus()` (available ↔ on_hold), `adjust()`, `openingBalance()`.
- `reverse(StockTransaction $tx, string $reason): StockTransaction` creates an exact opposite transaction and links both ways. A transaction can only be reversed once.

Reservations in a separate `StockReservations` service: `reserve()`, `release()`, `consume()` (consume happens inside a `pick` posting). Allocation strategy (FIFO/FEFO) is NOT built here, it comes in Module 6.

**Rules enforced on every post (in one `DB::transaction`):**
- Lock affected balance rows with `lockForUpdate` before changing them; create missing balance rows safely under concurrency.
- Convert entered qty to base UoM with `UomConverter`.
- No negative on hand, ever. No outbound qty larger than available (on hand − allocated) unless the line consumes its own reservation.
- Paired movements (move, changeOwner, changeStatus) must net to zero per item/batch.
- Item must be active. Batch required if `track_batch`; expiry required on new batches if `track_expiry`.
- Inbound to a location: location must be `active` (not `blocked`). Inbound to a `quarantine` location forces `stock_status = on_hold`.
- VW access: the posting user must have access to every VW on the lines (unrestricted roles pass).
- Idempotency: same `idempotency_key` returns the existing transaction instead of posting twice.
- Fire a `StockPosted` event after commit (used by later modules for webhooks and notifications).

**Immutability:**
- `StockMovement` and `StockTransaction` models throw on `update` and `delete` (model events).
- Enable the skipped arch test: only `App\Services\Inventory` may write `StockMovement`, `StockTransaction`, `StockBalance` and `StockReservation`.

**Hooks from Module 1, now real:**
- `Item::hasTransactions()` returns true if any movement exists.
- `Location::hasStock()` returns true if any balance has on hand ≠ 0.
- Item `track_batch`/`track_expiry` lock once the item has stock.

## Step 6 - Verify and rebuild
- `php artisan wms:stock:verify` recomputes every balance from the ledger and reservations and lists mismatches. `--fix` rebuilds balances from the ledger (logged in the activity log).
- Schedule it nightly. On mismatch, log an error and notify System Admins (database notification is enough for now).

## Step 7 - Screens
- **Stock on Hand** (replace the placeholder): filters for item, category, location/zone, batch, VW, status, expiring within X days. Columns: SKU (mono), item name, batch, expiry, location (mono), VW, status, on hand, allocated, available, UoM. Toggle "group by item" (totals per item). Low-stock items (below min) highlighted. Export button can be a disabled placeholder ("Module 10").
- **Movement History** (new, read-only): ledger list with filters (date range, type, item, location, VW, reference no., user). Click a transaction to open a drawer or modal with header and all lines. Reversed transactions are clearly marked.
- **Item detail, Stock tab**: balances for that item by location/batch/VW, plus last 20 movements.
- **Location detail or row action "View stock"**: what is in that location.
- **Opening Balance** (System Admin only): multi-line form (item, batch, expiry, location, VW, qty, UoM) posted as one `opening_balance` transaction. Preview totals before posting. This is how I will load test stock.
- **Reverse** row action on a transaction (System Admin only), with a required reason, via the confirm modal.

All lists are VW-scoped: a department user only sees their own VW's stock and movements.

## Step 8 - Permissions
`stock.view`, `stock.movements.view`, `stock.opening-balance`, `stock.reverse`, `stock.verify`.
- System Admin: all.
- Warehouse Manager: view, movements.view, verify.
- Storekeeper and Viewer/Auditor: view, movements.view.
- Department User: view (own VW only).
- Scanner Operator: none for now.
  Register new pages in `config/navigation.php`. Print the updated matrix.

## Step 9 - Seeder
`StockDemoSeeder` (separate, run after `FoundationDemoSeeder`): post opening balances through `StockLedger` (never insert rows directly) for most demo items across several locations and VWs, including batches with expiry dates (some expiring within 30 days), some stock in quarantine, and a few items below minimum.

## Step 10 - Tests (Pest)
At minimum:
- Balance always equals the sum of ledger lines (property-style test over many random postings).
- Negative stock rejected; outbound above available rejected; reservation consumption allowed.
- Move, change owner and change status net to zero and update both balances.
- Quarantine inbound forces `on_hold`; blocked location rejects inbound.
- Batch and expiry rules by item tracking flags.
- UoM conversion: posting 2 CTN of a 24-PCS item adds 48 base units.
- Idempotency key prevents double posting.
- Reversal creates the exact opposite and cannot be reversed twice.
- Movements and transactions cannot be updated or deleted.
- Reservations: reserve reduces available, release restores it, cancelled reservation frees stock.
- VW scope: department user sees only own VW balances and movements; 404 on another VW's transaction.
- `wms:stock:verify` detects a manually corrupted balance and `--fix` repairs it.
- Arch test enabled and passing.
- Concurrency (Pest group `mysql`, skipped on SQLite): two parallel outbound postings on the same balance cannot both succeed when stock only covers one.
- Module 1 hooks: item with movements cannot be deleted; location with stock cannot be deleted.

## Step 11 - Manual test checklist
Write `docs/testing/module-02.md`: post opening balances, check Stock on Hand math, view movements, reverse a transaction, check VW isolation as each role, run the verify command.

## Deliverable
- Tables, models, enums, DTOs, services and the public `StockLedger` API with a short usage example per helper.
- Updated permission matrix.
- Files changed and test results (`php artisan test` 0 failures, mysql group result, `npm run build`).
- Any spec ambiguity and the decision taken.

Stop when done. Do not start Module 3.
