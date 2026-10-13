# Module 3: Container Management

## Context
- Laravel + Livewire WMS. Done: Module 1 (Foundation), Module 2 (Stock Ledger), Module 2B (Serials + Data Import).
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` section 1 (Container Management), plus section 6 (VW rules for containers) and 11 (notifications, dashboard).
- This module does NOT move stock. No `StockLedger` calls. Receiving (GRN), variance and putaway are Module 4; this module prepares the expected lines Module 4 will receive against.
- Replace the existing Containers & Yard placeholder pages with real data. Use existing UI components (`x-ui.table`, row-actions, modals, badges, empty states), brand and semantic tokens only, glass styles, light and dark mode.

## Step 1 - Inspect and plan first
Report the existing placeholder pages and routes for containers and yard, then propose tables, enums, services/actions, screens and permissions. Wait for my confirmation.

## Step 2 - Tables
- `containers`:
    - `number` (internal, from `DocumentNumber` type CTR, e.g. `CTR-2026-0001`)
    - `container_no` (e.g. `MSKU1234567`), `seal_no`, `size_type` (enum: `20GP`, `40GP`, `40HC`, `20RF`, `40RF`, `other`)
    - `bl_no`, `vessel`, `voyage`
    - `shipper_partner_id`, `consignee_partner_id`, `shipping_line_partner_id` (all nullable FK partners)
    - `eta`, `arrived_at`, `arrival_seal_no`, `seal_verified` (bool, nullable), `free_days` (nullable int, demurrage-free days)
    - `yard_location_id` (nullable FK locations, must be type `yard_slot`)
    - `status` (string + enum cast), `unloading_started_at`, `unloaded_at`, `released_at`, `released_to_partner_id`, `release_notes`
    - `notes`, `created_by`, timestamps, soft deletes
    - Unique `container_no` among **active** containers only (not released or cancelled). Enforce in validation and with a DB-level guard (e.g. generated column `active_container_no` = container_no when active, else NULL, with a unique index).
- `container_expected_lines`: `container_id`, `line_no`, `item_id` (nullable until mapped), `raw_sku`, `raw_description`, `qty`, `uom_id`, `batch_no`, `expiry_date`, `weight_kg`, `virtual_warehouse_id`, `notes`. (Received quantities come in Module 4.)
- `container_status_history`: `container_id`, `from_status`, `to_status`, `user_id`, `notes`, `created_at`.
- `container_yard_moves`: `container_id`, `from_location_id`, `to_location_id`, `user_id`, `reason`, `created_at`.
- `container_discrepancies`: `container_id`, `expected_line_id` (nullable), `type` (enum: `seal_mismatch`, `short`, `excess`, `damaged`, `unexpected_item`, `other`), `description`, `reason_code_id` (nullable), `reported_by`, `resolved_at`, `resolved_by`, `resolution_notes`, timestamps. (Line-level types are recorded in Module 4; this module creates `seal_mismatch` and `other`.)
- `container_documents`: `container_id`, `type` (enum: `bill_of_lading`, `packing_list`, `photo`, `inspection_report`, `other`), `original_name`, `path` (private storage), `mime`, `size`, `uploaded_by`, timestamps.

## Step 3 - Status workflow
Backed enum `ContainerStatus` with `canTransitionTo()`. Every transition goes through `ContainerWorkflow` (service), runs in a transaction, writes `container_status_history` and the activity log.

| From | To | Action | Rules |
|---|---|---|---|
| — | `expected` | Create | Container no. valid; not active elsewhere |
| `expected` | `arrived` | Confirm arrival | Actual date/time required; enter seal no. seen on arrival. If it differs from expected seal: create `seal_mismatch` discrepancy, notify Warehouse Managers, still allow arrival. |
| `arrived` | `parked` | Park | Yard slot required, must be an active `yard_slot` with no other active container |
| `parked` | `parked` | Move slot | New free slot; recorded in `container_yard_moves` with reason |
| `parked` or `arrived` | `unloading` | Start unloading | Must have at least one expected line, and all lines mapped to an item and VW. Fires `ContainerUnloadingStarted` event (Module 4 will create the GRN from it). |
| `unloading` | `unloaded` | Mark unloaded | Blocked in this module with message "Available after Receiving (Module 4)". Implement through a `ContainerReceivingGate` interface; this module's default implementation always returns "not ready". Module 4 will replace it with the real GRN check. |
| `unloaded` | `released` | Release / return empty | Release date and returned-to (shipping line) required. Frees the yard slot. |
| `expected` | `cancelled` | Cancel | Reason required; only before arrival |

- A container can't go to `unloading` without being `arrived` or `parked` (spec rule).
- Yard slot is freed when a container is released or cancelled.

## Step 4 - Container number validation
- Validate ISO 6346 format: 3-letter owner code + category letter (U, J or Z) + 6 digits + check digit, with the check digit calculated per ISO 6346.
- Uppercase and strip spaces before saving.
- Invalid check digit: block, unless a Warehouse Manager or System Admin ticks "Override (non-standard number)" with a reason (logged).

## Step 5 - Packing list import (expected lines)
Reuse the Module 2B import framework (`data_imports`, validate/confirm jobs, review screen, error report) with a new import type `container_packing_list`, attached to one container.
- Client files vary, so add a **column mapping step** after upload: show detected headers and let the user map them to fields (SKU, description, qty, UoM, batch, expiry, weight, department/VW). Required: SKU or description, qty.
- Save mappings as reusable **mapping profiles** per shipper (`import_mapping_profiles`: name, partner_id, import_type, mapping json). Auto-apply the shipper's profile when one exists.
- Validation:
    - Known SKU or matching barcode → linked to the item.
    - Unknown SKU → flagged. In the review step the user can **map to an existing item** (searchable select) or **create a new item** (quick-create modal with SKU, name, category, base UoM, tracking flags; requires `items.create`).
    - UoM must exist and be valid for the item; VW must exist (or default to the item's default VW); batch and expiry required if the item tracks them (expiry may be blank if unknown on the packing list, flagged as a warning, not an error, since it's confirmed at receiving).
- Confirm replaces or appends expected lines (user chooses), only while the container is `expected`, `arrived` or `parked`.
- Expected lines can also be added and edited manually on the container (modal), with the same rules.

## Step 6 - Active containers import
Add import type `active_containers` (deferred from Module 2B): container_no, seal_no, size_type, bl_no, vessel, voyage, shipper_code, consignee_code, shipping_line_code, eta, status (`expected`, `arrived` or `parked`), arrived_at, yard_slot_code. Same import flow and rules as Step 3/4. Add it as card 7 on the Data Import page ("Needs first: Partners, Locations").

## Step 7 - VW and department visibility
- Containers don't have a single VW; one container can carry goods for several departments (spec).
- A department user sees a container only if at least one expected line belongs to one of their VWs, and inside it only their own lines.
- Implement as a query scope used by every container query (`ContainerQueryService`), not just in the UI. Opening another department's container by URL returns 404.
- Unrestricted roles see everything.

## Step 8 - Dwell time and settings
- Dwell days = days from `arrived_at` to `released_at` (or now if not released).
- Add to Settings → Operations: `container_dwell_warning_days` (default 7), `container_dwell_critical_days` (default 14), `default_free_days` (default 7).
- Demurrage days = max(0, dwell − free days). Show it; no money calculation.

## Step 9 - Screens
1. **Containers list** (replace placeholder): filters for status (segmented tabs with counts), VW/department, shipping line, ETA range, dwell (normal / warning / critical); search by container no., BL, vessel, internal number. Columns: container no. (mono) with size type below, BL (mono), vessel/voyage, shipping line, ETA, arrival, yard slot (mono chip), dwell days (badge: neutral / amber / red), status, actions. Row actions follow the workflow: View, Edit, Confirm arrival, Park, Move slot, Start unloading, Release, Cancel (only valid ones shown).
2. **New / Edit container**: full page (many fields); partners as searchable selects filtered by partner type.
3. **Container detail**: header with container no., status badge, dwell badge and action buttons; tabs:
    - Overview (all fields, seal check result, yard slot)
    - Expected lines (table, totals per VW, add/edit/delete line, Import packing list button, unmapped lines highlighted)
    - Documents (upload with type, list, download, delete; images previewed)
    - Discrepancies (list, add `other`, resolve with notes)
    - History (status changes and yard moves timeline)
    - Received & Variance (placeholder: "Available in Module 4")
4. **Modals**: Confirm arrival (date/time, seal seen, live match/mismatch indicator), Park / Move slot (only free yard slots, grouped by zone), Release (date, returned to, notes), Cancel (reason).
5. **Yard map**: grid of all yard slots grouped by zone. Each slot tile shows slot code and, if occupied, container no., size type and dwell days. Colours: free = neutral, occupied = brand tint, dwell warning = amber, critical = red, blocked slot = striped/muted. Click a tile → side panel with container summary and actions. Filters: zone, show free only.
6. **Dwell dashboard**: summary cards (in yard, avg dwell, over warning, over critical, total demurrage days) and a table sorted by dwell descending.
7. **Main dashboard**: replace placeholder "Active Containers" stat and "Inbound Pipeline" table with real data (VW-scoped).

## Step 10 - Notifications
Database notifications (bell in topbar) plus email:
- Seal mismatch on arrival → Warehouse Managers.
- Container arrived → Warehouse Managers and users of VWs with lines on it.
- Container passes dwell warning or critical threshold → Warehouse Managers (daily scheduled check, notify once per threshold).
  Use queued notifications. Mark-as-read in the bell dropdown.

## Step 11 - Permissions
`containers.view`, `containers.create`, `containers.update`, `containers.arrive`, `containers.park`, `containers.unload`, `containers.release`, `containers.cancel`, `containers.documents`, `containers.discrepancies`, `containers.override-number`.

| Role | Access |
|---|---|
| System Admin | All |
| Warehouse Manager | All |
| Storekeeper | view, arrive, park, unload, documents, discrepancies (add only) |
| Department User | view (own VW lines only) |
| Viewer / Auditor | view |
| Scanner Operator | none (scanner screens come in Module 5) |

Re-add `containers.view` to Department User with the VW scope from Step 7. Register pages in `config/navigation.php` (Inbound group: Containers, Yard Map, Dwell Time). Print the matrix.

## Step 12 - Seeder
`ContainerDemoSeeder` (after Foundation and Stock seeders): ~12 containers across all statuses, valid ISO numbers, some with multi-VW expected lines, one with a seal mismatch, a few over warning/critical dwell, some unmapped packing list lines, yard slots partly occupied.

## Step 13 - Tests (Pest)
- ISO 6346 check digit: valid and invalid numbers; override requires permission and reason.
- Active container number uniqueness; reuse allowed after release or cancel.
- Every allowed and forbidden status transition; history written each time.
- Seal mismatch creates discrepancy and notification, arrival still succeeds.
- Park: only free active yard slots; move records history; release frees slot.
- Start unloading blocked when lines missing or unmapped; fires event when allowed.
- Unloaded blocked by default gate.
- Packing list import: column mapping, profile reuse per shipper, unknown SKU flagged, map-to-existing and create-new, UoM and tracking rules, append vs replace.
- Active containers import.
- VW visibility: department user sees only containers with their lines and only their lines; 404 on others.
- Dwell and demurrage calculation; threshold notification sent once.
- Permissions per role per action (Livewire tests).
- No raw `<table>` and no off-brand colours (existing guard tests still pass).

## Step 14 - Manual test checklist
Write `docs/testing/module-03.md`.

## Deliverable
Tables, enums, workflow table, services, screens, permission matrix, files changed, test results (`php artisan test` 0 failures, `mysql` group, `npm run build`), and decisions taken.

Stop when done. Do not start Module 4.
