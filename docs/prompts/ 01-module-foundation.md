# Module 1: Foundation (business logic)

## Context
- Laravel + Livewire WMS. UI shell, glassmorphism theme, auth, users, roles (spatie/laravel-permission), 2FA, virtual warehouses and the `BelongsToVirtualWarehouse` scope are done.
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first and follow the conventions strictly. Update `PROJECT-BRIEF.md` when done.
- Functional spec: `docs/WMS_Functional_Breakdown.md`, section 0 (Foundation). Read it fully.
- The existing Livewire pages use placeholder data. Replace the placeholders with real data and logic. Keep the current UI design and components.
- General-purpose system: no client names hardcoded anywhere, including seeders.

## Build approach
We build one module, I test it, then we move on. Do not start anything from later modules (ledger, containers, GRN, etc.) beyond what this prompt lists.

## Step 1 - Inspect and plan first
1. List the existing placeholder pages for items, locations, partners and settings.
2. Propose migrations, models, services, enums and the permission list for this module.
3. Wait for my confirmation before writing code.

## Step 2 - Settings
- `settings` (key/value, typed, cached): company name, logo, address, TIN, phone, email, default allocation rule (`fifo`/`fefo`), variance approval threshold (% and qty), adjustment approval threshold.
- `document_sequences`: `type` (GRN, DO, ADJ, TRF, CNT, LPN, CTR), `prefix`, `include_year`, `padding`, `next_number`, `reset_yearly`. Format example: `GRN-2026-0001`.
- `DocumentNumber::next(string $type)` uses a row lock (`lockForUpdate`) so two concurrent requests can never get the same number. Write a test for this.
- `reason_codes`: `code`, `name`, `applies_to` (variance, adjustment, short_pick, reprint, hold, seal_mismatch), `is_active`. Seed sensible defaults.
- `units_of_measure`: `code`, `name`, `type` (count, weight, volume, length). Seed PCS, CTN, PLT, KG, G, L, ML, M, BOX, BAG.
- Settings UI: company profile, number sequences, reason codes, UoM. System Admin only (`settings.manage`).

## Step 3 - Partners
- `partners`: `code` (unique), `name`, `type` (supplier, shipper, consignee, customer, shipping_line, transporter; multiple allowed via JSON or pivot), `tin`, `vrn` (nullable), contact person, phone, email, address, city, country, `is_active`, soft deletes.
- Partners are global (not VW-scoped).
- List with filter by type and status, search, create/edit, deactivate.

## Step 4 - Item master (SKU)
Follow spec 0.1 exactly.
- `items`: `sku` (unique, manual or auto from sequence), `name`, `description`, `category_id`, `base_uom_id`, `length/width/height` (cm), `weight` (kg), `track_batch`, `track_expiry`, `track_serial`, `min_stock`, `max_stock`, `default_virtual_warehouse_id`, `is_active`, soft deletes.
- `item_categories`: `name`, `parent_id` (nullable), `is_active`.
- `item_uoms`: `item_id`, `uom_id`, `factor_to_base` (e.g. CTN = 24 PCS, PLT = 960 PCS), `is_default_purchase`, `is_default_issue`. The base UoM always has factor 1 and cannot be removed.
- `item_barcodes`: `item_id`, `barcode` (globally unique), `uom_id` (which pack it identifies), `type` (supplier, internal), `is_primary`.
- `UomConverter` service: convert any qty between an item's UoMs and to base. Write unit tests including rounding.
- Rules (from spec):
    - Turning on `track_expiry` forces `track_batch` on.
    - An item with transactions cannot be deleted, only deactivated. For now "has transactions" checks a `hasTransactions()` method that returns false; later modules will extend it. Put a clear `// extended in Module 2+` comment.
    - `track_batch` and `track_expiry` cannot be switched off once the item has stock (same hook).
- UI: list (search by SKU, name or barcode; filter by category, status, VW), create/edit with tabs (General, Units & Packing, Barcodes, Tracking, Stock Levels), detail page.

## Step 5 - Warehouse layout (locations)
Follow spec 0.2.
- `warehouses`: `code` (unique, short e.g. `DAR`), `name`, `address`, `is_active`.
- `locations` (single self-referencing table):
    - `warehouse_id`, `parent_id` (nullable), `level` enum (`zone`, `aisle`, `rack`, `shelf_level`, `bin`), `segment_code` (e.g. `A`, `03`, `B`), `code` (full code, unique per warehouse, built automatically by joining ancestor segment codes with `-`, e.g. `DAR-A-03-02-B`).
    - `type` enum: `yard_slot`, `receiving_dock`, `storage`, `staging`, `quarantine`.
    - `status` enum: `active`, `blocked`, `full`.
    - `max_pallets`, `max_weight_kg` (nullable), `barcode` (defaults to `code`), `path` (materialised path for subtree queries), `is_pickable` (false for quarantine, yard and receiving dock).
    - Levels may be skipped (a floor zone can hold bins directly under the zone).
- Rules:
    - Changing a parent's segment code rebuilds child codes in one transaction.
    - A location with child locations or stock cannot be deleted (stock check via a `hasStock()` hook, false for now, extended in Module 2).
    - Quarantine locations are never pickable.
- **Bulk generator:** create a range in one action, e.g. Zone A, aisles 01-10, racks 01-05, levels 01-04, bins A-D. Preview the count and the first and last codes before confirming. Skip codes that already exist.
- UI: tree view (expand/collapse) plus a flat filterable list (type, status, zone), create/edit, block/unblock with reason, bulk generator. Yard slots appear on the Containers & Yard page later, so just make sure they can be created here.

## Step 6 - Permissions and seeding
- Add permissions: `settings.manage`, `partners.view|create|update|delete`, `items.view|create|update|delete`, `locations.view|create|update|delete`, `locations.block`. Map them to the existing roles following the role table in the spec, and print the matrix in the deliverable.
- `FoundationDemoSeeder` (separate from production seeders): 1 warehouse, 15 items across 4 categories (with and without batch/expiry tracking) spread over the existing VWs, UoM conversions and barcodes, 10 partners, a location tree (yard with 20 slots, 2 receiving docks, 2 staging bays, 1 quarantine area, storage zones A and B with bins). Use generic names only.

## Step 7 - Tests (Pest)
At minimum:
- Document number sequence: formatting, yearly reset, concurrency (no duplicates).
- VW scope on items: department user sees only items in their VWs; admin/manager see all; partners stay global.
- Item rules: expiry forces batch, base UoM factor = 1, barcode uniqueness across items, UoM conversion math.
- Location rules: code generation, rebuild on parent rename, bulk generator skips duplicates, quarantine not pickable, cannot delete with children.
- Deactivate instead of delete for referenced records.
- Permissions: each role against each new route (200 vs 403).

## Step 8 - Manual test checklist
Write `docs/testing/module-01.md`: a step-by-step checklist I can follow in the browser, logged in as each role, covering every screen and rule in this module, with the expected result for each step.

## Deliverable
- Migrations, models, services and enums created.
- Role/permission matrix.
- Files changed.
- Test results summary.
- Anything in the spec that was unclear and the decision you took.

Stop when done. Do not start Module 2 until I confirm testing passed.
