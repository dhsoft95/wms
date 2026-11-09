# Barcode Scanner Audit
**Date:** 2026-11-09  
**Auditor:** Claude (automated static analysis)  
**Scope:** USB/Bluetooth keyboard-wedge and Android scanner integration across the WMS  
**Goal:** Every operational page that handles items, locations, LPNs, serials or documents should accept a scan directly. Mobile /scan screens remain for Scanner Operators.

---

## Section 1 — Summary

### What works
- `BarcodeResolver` correctly handles LPN-, CTR-, ISO-6346, GRN-, PO- prefixes, GS1-128/DataMatrix (AIs 01/10/17/21/30/37), location code/barcode, item barcodes, SKU, and serial lookups.
- `x-scan-input` component is well-designed: debounce, beep, focus return, `scan-success`/`scan-error` event wiring.
- `scan.blade.php` layout has offline banner, global ScanFeedback JS (beep + flash + vibrate), device code in header.
- `ScannerDeviceService` auto-registers new devices, blocks deactivated ones, records last user and last-seen timestamp.
- `ScannerOnly` middleware correctly restricts scanner-operator role to `/scan/*` routes.
- Three desktop pages already use `x-scan-input` properly: GRN Tally, Warehouse/Putaway, and the putaway execute modal.

### What is broken (must fix before go-live)
1. **`DO-` barcode returns a hard-coded error** ("Dispatch Orders not available in this module") — scanner Dispatch and desktop Dispatch/Gate pages can't scan a DO barcode.
2. **Camera scan button is dead** — `scan.blade.php` calls `window.startCameraScan()` which is never defined; button silently does nothing.
3. **`Scan/StockLookup` is a stub** with hard-coded data (`SKU-FL-001`, bin `A-12-03`). The route `/scan/stock-lookup` also points to `Scan\Lookup`, not `Scan\StockLookup`, so the stub is unreachable but Lookup is served twice.
4. **`Scan/Pick` has no barcode scanning** — only typed DO/pick-list number; no bin scan, no item scan, no LPN scan per the spec sequence (bin → item/LPN → qty → serial).
5. **`Scan/Dispatch` has no load-check scan** — skips the entire LPN scan sequence; just enters vehicle details and confirms dispatch for the whole DO.
6. **`Scan/Move` hardcodes `virtualWarehouseId: 1`** in partial-item moves — multi-VW warehouses will silently move stock against the wrong VW.
7. **`Scan/Receive` writes `GrnReceipt` directly** (bypasses `GrnWorkflow`), missing serial number validation loop, no idempotency at the GrnWorkflow level.

### Top 5 gaps
1. **Storekeeper cannot scan** — `scan.receive` and `scan.putaway` are missing from the storekeeper role. Only `scanner-operator` can receive/putaway on scanner. This contradicts the spec (Storekeeper executes receiving, putaway, picking, dispatch).
2. **`DO-`, `PCK-`, `DSP-`, `GP-` prefixes not resolvable** — scannable documents printed by Module 6 labels cannot be resolved by `BarcodeResolver`.
3. **No global keystroke capture** — desktop pages with `x-scan-input` only work if the input field has focus. A wedge scanner fired at the wrong moment loses the scan silently.
4. **Tab terminator not handled** — many hardware scanners default to `Tab` as their suffix; `x-scan-input` only listens for `Enter`.
5. **No scan audit log** — raw barcode value, resolved type, page, and device are not logged per scan. `StockLedger::move()` has no `device` parameter, so scanner relocations are recorded as `device='desktop'`.

---

## Section 2 — Scan Infrastructure Findings

### 2.1 `x-scan-input` component (`resources/views/components/scan-input.blade.php`)

**How it works:**
- Alpine.js `x-data` block with `status` (null/ok/err), `beep()`, `flash()`.
- `keydown.enter.prevent` fires `$wire.{method}()`, clears the field, re-focuses after the Livewire round-trip via `.then(() => $nextTick(() => $el.focus()))`.
- Dispatches its own beep inline; also listens to `@scan-success.window` / `@scan-error.window` for feedback from the Livewire component.
- Duplicate-scan guard: suppresses same value within 600 ms window.

**Defects:**
| # | Defect | Impact |
|---|--------|--------|
| D1 | Only `keydown.enter` handled; no `keydown.tab` | Scanners with Tab suffix silently type a tab character into the page |
| D2 | Focus return is in `.then()` — lost if Livewire is slow (>500 ms) or errors | Next scan fires into the focused browser chrome; wedge scanner appears broken |
| D3 | Error feedback is hardcoded "Not recognised" in the Alpine template | Server-side error messages (e.g., "GRN is not in Receiving status") are not shown inline |
| D4 | No global keystroke capture fallback | If user clicks a table row or modal, scanner fires into void |
| D5 | 600 ms duplicate guard applies to ALL repeated scans of the same barcode | Legitimate re-confirmation of same LPN (e.g., scan to count twice) suppressed silently |

### 2.2 `scan.blade.php` layout (`resources/views/components/layouts/scan.blade.php`)

**How it works:**
- Mobile-first header with device code from session. Offline banner via Alpine. Global `ScanFeedback` JS object (beep, vibrate, flash) wired to `livewire:initialized` for `scan-success`/`scan-error` Livewire events.
- Camera button in header triggers `window.startCameraScan()`.

**Defects:**
| # | Defect | Impact |
|---|--------|--------|
| D6 | `window.startCameraScan` is never defined anywhere in the codebase | Camera button is completely non-functional (spec requires BarcodeDetector/ZXing fallback) |
| D7 | Offline banner is visual only; no JS code actually disables Livewire actions when offline | Operator gets a confusing network error instead of a friendly message when scanning offline |
| D8 | `scan_device_code` session value set by `ScannerDeviceService` is not refreshed on each scan | If the device is deactivated mid-session, the session still shows it active |

### 2.3 `BarcodeResolver` (`app/Services/Scanning/BarcodeResolver.php`)

**Supported formats in check order:**
1. GS1-128/DataMatrix (AIs 01, 10, 17, 21, 30, 37) → resolves GTIN to `ScanResultType::Item`
2. `LPN-` prefix → `ScanResultType::Lpn`
3. `CTR-` prefix → `ScanResultType::Container`
4. ISO-6346 (`[A-Z]{4}\d{7}`) + check digit → `ScanResultType::Container`
5. `GRN-` prefix → `ScanResultType::Grn`
6. `PO-` prefix → `ScanResultType::PurchaseOrder`
7. `DO-` prefix → **hardcoded `ScanResultType::Unknown` error** ("Dispatch Orders not available in this module")
8. Multi-fallback: Location (by `.barcode` or `.code`), Item (by `item_barcodes.barcode`), Item (by `items.sku`), Serial (by `serial_no`)

**Gaps vs labels actually printed:**

| Label type | Barcode encoded | Resolver result |
|-----------|----------------|----------------|
| Pallet / LPN | `LPN-XXXX` | ✅ Resolved |
| Item / carton | SKU or EAN-13 | ✅ Resolved |
| Location / bin | Location code | ✅ Resolved |
| Container (internal) | `CTR-XXXX` | ✅ Resolved |
| Container (ISO) | `MSKU1234567` | ✅ Resolved (with check digit) |
| GRN document | `GRN-XXXX` | ✅ Resolved |
| Purchase Order | `PO-XXXX` | ✅ Resolved |
| **Delivery Order** | **`DO-XXXX`** | ❌ Hard error ("not available") |
| **Pick List** | **`PCK-XXXX`** | ❌ Falls through to Unknown |
| **Dispatch** | **`DSP-XXXX`** | ❌ Falls through to Unknown |
| **Gate Pass** | **`GP-XXXX`** | ❌ Falls through to Unknown |
| Serial number | Arbitrary string | ✅ Resolved (via `serial_no` lookup) |

**Other resolver issues:**
- **AIM symbology identifiers not fully stripped:** `]C1`, `]d2`, `]Q3` are stripped, but `]C0` (Code 128 without FNC1), `]E0`/`]E3` (EAN-13/EAN-8), `]Q0` (QR), `]I0` (ITF) would pass verbatim and fail prefix matching.
- **Case sensitivity:** `Location::where('barcode', $raw)` and `where('code', $raw)` — MySQL with `utf8_general_ci` is case-insensitive, but explicit `upper()` check is missing for safety.
- **`ScanContext::$expects` does not enforce VW scoping at resolver level** — `resolveMulti()` returns items and serials without filtering by VW; the Livewire component must do its own VW check after resolving.
- **GS1 parsing stops on unknown AI** (`$i = $len; break`) — a barcode with an AI not in `[01,10,17,21,30,37]` silently truncates remaining fields instead of skipping them.
- **No Tab/CRLF suffix stripping** — `trim()` on `$raw` handles trailing spaces, but `\r\n` (CRLF, common from some Zebra scanners in keyboard-wedge mode) is not stripped before prefix checks.

### 2.4 `ScanContext` (`app/Services/Scanning/ScanContext.php`)

Three fields: `$expects` (expected type), `$warehouseId`, `$virtualWarehouseId`. None of the mobile scan screens actually pass `warehouseId` or `virtualWarehouseId` to the context. The VW enforcement is done manually in each Livewire component after resolution — inconsistently applied.

### 2.5 `ScanResult` (`app/Services/Scanning/ScanResult.php`)

Clean readonly DTO. `ok()` correctly returns false for Unknown and Ambiguous. `$candidates` populated on Ambiguous for picker UI. No issue.

### 2.6 `RelocationService` (`app/Services/Scanning/RelocationService.php`)

Correctly wraps in `DB::transaction`, uses `StockLedger`. **Defect:** Neither `moveLpn()` nor `moveItem()` passes a `device` parameter — the StockPosting defaults to `'desktop'`, so scanner relocations are recorded as desktop operations in the ledger.

### 2.7 `ScannerDeviceService` (`app/Services/Scanning/ScannerDeviceService.php`)

Auto-registers device on first visit, blocks deactivated devices, updates `last_user_id`/`last_seen_at`. **Defect:** Device code increment uses `ScannerDevice::max('code')` which does string comparison on `SCN-NNN`; will break at `SCN-100` (string "SCN-100" < "SCN-099" is false but "SCN-100" < "SCN-099" alphabetically depends on collation). Safe up to `SCN-099`, then ordering fails.

### 2.8 `ScannerOnly` middleware

Registered in the global `web` middleware group. Correctly redirects `scanner-operator` users to `/scan/home` for all non-scan routes. No issues.

### 2.9 `ItemBarcodeController` / `ItemBarcodeType` / `ItemBarcode` model

`ItemBarcodeType` has two values: `supplier` and `internal`. Both are searched by `BarcodeResolver`. `ItemBarcodeController` renders PNG and PDF labels via `LabelRenderer`. No scan-related defects.

### 2.10 `LpnService` (`app/Services/Inbound/LpnService.php`)

Referenced by `Scan/Receive` to generate new LPNs. No scan-related defects observed.

---

## Section 3 — Page-by-Page Classification

> Status codes: **A** = already has scan, **B** = needs scan, **C** = no scan needed.
> Effort: **S** = < 1 hour (drop in `x-scan-input`, call existing method), **M** = 2–4 hours (new `processScan` method + resolver call), **L** = 1–2 days (new service method or multi-step scan flow needed).

### 3.1 Mobile /scan Screens

| Page | Route | Component | Status | What is scanned | Where on page | Service method | Notes |
|------|-------|-----------|--------|----------------|---------------|----------------|-------|
| Scan Home | `/scan` | `Scan\Home` | A | — | — | `ScannerDeviceService::resolveDevice` | ✅ No scan input needed |
| Scan Receive | `/scan/receive` | `Scan\Receive` | A (partial) | GRN, Item/barcode, LPN | Step inputs | `BarcodeResolver` ✓; `LpnService` ✓ | ❌ Writes `GrnReceipt` directly — bypasses `GrnWorkflow`; missing serial loop; GS1 qty pre-fills but no batch/expiry validation |
| Scan Putaway | `/scan/putaway` | `Scan\Putaway` | A (partial) | Item barcode or LPN, then bin | Step inputs | `BarcodeResolver` ✓; `PutawayEngine` ✓ | ❌ Spec says scan LPN first; current impl scans item then picks task; LPN scan step missing |
| Scan Pick | `/scan/pick` | `Scan\Pick` | B (stub) | DO number / PCK number, then bin, item, qty, serial | Typed input only; no scanner | `PickingService::executeTask` | ❌ No BarcodeResolver; no bin scan; no item scan; just DO number typed |
| Scan Dispatch | `/scan/dispatch` | `Scan\Dispatch` | B (stub) | DO number, then each LPN loaded | Typed DO number; no LPN scan | `DispatchService::confirmDispatch` | ❌ No BarcodeResolver; no load-check LPN scan loop |
| Scan Move | `/scan/move` | `Scan\Move` | A (partial) | Source bin, LPN or item, dest bin | Step inputs | `BarcodeResolver` ✓; `RelocationService` ✓ | ❌ Hardcoded VW=1 for item moves; device not passed to ledger |
| Scan Lookup | `/scan/lookup` | `Scan\Lookup` | A (partial) | Any barcode type | Single scan input | `BarcodeResolver` ✓; direct model queries | ❌ Stock queries not VW-scoped (shows all VW stock to any user) |
| Scan StockLookup | `/scan/stock-lookup` | `Scan\Lookup` | A (broken) | — | — | — | ❌ Route points to `Scan\Lookup`, not `Scan\StockLookup`; `Scan\StockLookup` is a dead stub with hardcoded data |
| Scan Gate Check | `/scan/gate` | `Scan\GateCheck` | B (partial) | GP number | Typed input | `DispatchService::verifyGateExit` | ❌ No BarcodeResolver; plain string lookup; not using `x-scan-input` |

### 3.2 Desktop — Inbound

| Page | Route | Component | Status | What is scanned | Where on page | Service method | Effort |
|------|-------|-----------|--------|----------------|---------------|----------------|--------|
| GRN Index | `inbound.grn.index` | `Inbound\Grn\Index` | C | — | — | — | — |
| GRN Create | `inbound.grn.create` | (Volt/form) | C | — | — | — | — |
| GRN Show | `inbound.grn.show` | (view only) | C | — | — | — | — |
| **GRN Receive** | `inbound.grn.receive` | `Inbound\Grn\Receive` | **A (defective)** | Item SKU/barcode | Custom raw `<input>` with Alpine `keydown.enter` | Custom SKU/barcode string match — NOT `BarcodeResolver` | **M** — replace custom input with `x-scan-input`; call `BarcodeResolver`; handle GS1 |
| **GRN Tally** | `inbound.grn.tally` | `Inbound\Grn\Tally` | **A ✅** | Item barcode/SKU, LPN | `x-scan-input` | `BarcodeResolver` → `processScan()` | Done |
| Containers Index | `inbound.containers.index` | `Inbound\Containers\Index` | C | — | — | — | — |
| Containers Create | `inbound.containers.create` | `Inbound\Containers\Create` | C | — | — | — | — |
| Container Show | `inbound.containers.show` | (view) | C | — | — | — | — |
| Yard / Dwell | `inbound.yard.index` | `Inbound\Yard\Index` | C | — | — | — | — |
| Inspections | `inbound.inspections.index` | `Inbound\Inspections\Index` | C | — | — | — | — |

### 3.3 Desktop — Warehouse

| Page | Route | Component | Status | What is scanned | Where on page | Service method | Effort |
|------|-------|-----------|--------|----------------|---------------|----------------|--------|
| **Putaway Index** | `inbound.putaway.index` | `Warehouse\Putaway\Index` | **A ✅** | Item barcode / LPN → opens execute modal | `x-scan-input` above task list | `BarcodeResolver` → `processScan()` | Done |
| Putaway Modal | (partial) | `Concerns\HasPutawayModal` | **A ✅** | Bin scan in execute modal | `x-scan-input` in `putaway-execute-modal.blade.php` | `BarcodeResolver` | Done |
| Warehouse Putaway (alt) | `warehouse.putaway.index` | `Warehouse\Putaway\Index` | **A ✅** | (same as inbound.putaway.index) | — | — | Done |
| **Movements Create** | `warehouse.movements.create` | `Warehouse\Movements\Create` | **B** | Item barcode (from bin field), bin code | From-bin and item search fields per line | Existing `MovementWorkflow::create` | **M** — add `x-scan-input` above lines table; resolve item and auto-fill line |
| Movements Index | `warehouse.movements.index` | `Warehouse\Movements\Index` | C | — | — | — | — |
| Movements Show | `warehouse.movements.show` | `Warehouse\Movements\Show` | C | — | — | — | — |
| **Transfers Create** | `warehouse.transfers.create` | `Warehouse\Transfers\Create` | **B** | Item barcode | Item search per line | Existing `TransferWorkflow` | **M** |
| Transfers Index | `warehouse.transfers.index` | `Warehouse\Transfers\Index` | C | — | — | — | — |
| Transfers Show | `warehouse.transfers.show` | `Warehouse\Transfers\Show` | C | — | — | — | — |
| WTR Index | `warehouse.wtr.index` | `Warehouse\Wtr\Index` | C | — | — | — | — |
| WTR Create | `warehouse.wtr.create` | `Warehouse\Wtr\Create` | **B** | Item barcode | Item search per line | Existing WTR workflow | **M** |
| WTR Show | `warehouse.wtr.show` | `Warehouse\Wtr\Show` | C | — | — | — | — |

### 3.4 Desktop — Inventory

| Page | Route | Component | Status | What is scanned | Where on page | Service method | Effort |
|------|-------|-----------|--------|----------------|---------------|----------------|--------|
| Stock Index | `inventory.stock.index` | `Inventory\Stock\Index` | C | — | — | — | — |
| **Stock Show** | `inventory.stock.show` | `Inventory\Stock\Show` | **B** | Item barcode (quick navigation) | Search/filter bar | `BarcodeResolver` → redirect to stock row | **S** |
| Stock Opening Balance | `inventory.opening-balance` | `Inventory\Stock\OpeningBalance` | C | — | — | — | — |
| Stock Movements | `inventory.movements.index` | `Inventory\Stock\Movements` | C | — | — | — | — |
| **Adjustments Create** | `inventory.adjustments.create` | `Inventory\Adjustments\Create` | **B** | Item barcode | Item search field per line | Existing `AdjustmentWorkflow` | **M** |
| Adjustments Index | `inventory.adjustments.index` | `Inventory\Adjustments\Index` | C | — | — | — | — |
| Adjustments Show | `inventory.adjustments.show` | `Inventory\Adjustments\Show` | C | — | — | — | — |
| **Counts Show** | `inventory.counts.show` | `Inventory\Counts\Show` | **B** | Item barcode (auto-enter count line) | Per-line count entry inputs | `CycleCountWorkflow::recordCount` | **M** — add `x-scan-input` above lines table; `BarcodeResolver` → find matching line → focus its qty field |
| Counts Index | `inventory.counts.index` | `Inventory\Counts\Index` | C | — | — | — | — |
| Counts Create | `inventory.counts.create` | `Inventory\Counts\Create` | C | — | — | — | — |
| **Quarantine Index** | `inventory.quarantine.index` | `Inventory\Quarantine\Index` | **B** | LPN or item barcode (find stock row) | Search/filter bar | `BarcodeResolver` → open release modal for matched balance | **S** |
| **Serials Lookup** | `inventory.serials.lookup` | `Inventory\Serials\Lookup` | **B** | Serial number barcode | Search field | `BarcodeResolver` → pre-fill search and trigger lookup | **S** |
| **SKU Tracker** | `inventory.sku-tracker.index` | `Inventory\SkuTracker\Index` | **B** | Item barcode (jump to SKU) | Search input | `BarcodeResolver` → `selectItem()` | **S** |
| Disposals Create | `inventory.disposals.create` | `Inventory\Disposals\Create` | **B** | Item barcode | Item search | `AdjustmentWorkflow` (disposal) | **M** |
| Disposals Index | `inventory.disposals.index` | `Inventory\Disposals\Index` | C | — | — | — | — |
| Disposals Show | `inventory.disposals.show` | `Inventory\Disposals\Show` | C | — | — | — | — |
| Stock Status Create | `inventory.stock-status.create` | `Inventory\StockStatus\Create` | C | — | — | — | — |
| Stock Status Show | `inventory.stock-status.show` | `Inventory\StockStatus\Show` | C | — | — | — | — |

### 3.5 Desktop — Outbound / Orders

| Page | Route | Component | Status | What is scanned | Where on page | Service method | Effort |
|------|-------|-----------|--------|----------------|---------------|----------------|--------|
| Orders Index | `outbound.orders.index` | `Outbound\Orders\Index` | C | — | — | — | — |
| Orders Create | `outbound.orders.create` | `Outbound\Orders\Create` | **B** | Item barcode (add line) | Item search per line | Existing `OrderForm` | **S** |
| Orders Edit | `outbound.orders.edit` | `Outbound\Orders\Edit` | **B** | Item barcode | Item search per line | Existing `OrderForm` | **S** |
| Orders Show | `outbound.orders.show` | `Outbound\Orders\Show` | C | — | — | — | — |
| Orders Index (old) | `orders.index` | `Orders\Index` | C | — | — | — | — |
| **Pick Lists Index** | `outbound.pick-lists.index` | `Outbound\PickLists\Index` | **B** | Pick list / DO number (open pick task) | Search/filter bar | `PickingService::executeTask` via modal | **M** — add `x-scan-input`; `BarcodeResolver` (once PCK- added) → open matching pick list |
| **Dispatch Index** | `outbound.dispatch.index` | `Outbound\Dispatch\Index` | **B** | DO number (open dispatch modal) | Search/filter bar + dispatch modal LPN scan | `DispatchService::confirmDispatch` | **L** — DO lookup + per-LPN load scan in modal |
| Dispatch Show | `outbound.dispatch.show` | `Outbound\Dispatch\Show` | C | — | — | — | — |
| **Gate Check** | `outbound.gate.check` | `Outbound\Gate\Check` | **A (defective)** | GP number | Raw `<input>` with `wire:keydown.enter="search"` | `DispatchService::verifyGateExit` | **S** — replace with `x-scan-input`; add `BarcodeResolver` for GP- prefix |
| POD Index | `outbound.pod.index` | `Outbound\Pod\Index` | C | — | — | — | — |

### 3.6 Desktop — Admin

| Page | Route | Component | Status | Notes |
|------|-------|-----------|--------|-------|
| Items Index | `admin.items.index` | `Admin\Items\Index` | C | Barcode search field is text search, not scan-driven; acceptable |
| Items Show | `admin.items.show` | `Admin\Items\Show` | C | — |
| Items Create | `admin.items.create` | `Admin\Items\Create` | C | — |
| Items Edit | `admin.items.edit` | `Admin\Items\Edit` | C | — |
| **Locations Index** | `admin.locations.index` | `Admin\Locations\Index` | **B** | Location barcode (jump to location) | Search bar | `BarcodeResolver` → `selectLocation()` for bulk label print | **S** |
| Labels Templates | `admin.labels.templates` | `Admin\Labels\Templates\*` | C | — | — | — |
| Scanner Devices | `admin.scanner-devices.index` | `Admin\ScannerDevices\Index` | C | — | — | — |

### 3.7 Ad-hoc scan inputs that should be replaced with `x-scan-input`

| File | Current implementation | Issue |
|------|------------------------|-------|
| `livewire/inbound/grn/receive.blade.php:43` | Raw `<input>` + Alpine `keydown.enter` + manual focus restore | No beep/flash feedback; no debounce; no duplicate guard; uses custom SKU/barcode match not `BarcodeResolver` |
| `livewire/outbound/gate/check.blade.php:16` | Raw `<input>` + `wire:keydown.enter="search"` | No feedback; no `BarcodeResolver`; no Tab handling |

---

## Section 4 — Resolver Gaps (labels printed but not resolvable)

| Barcode format | Example | Printed by | Resolver action | Fix required |
|---------------|---------|-----------|----------------|-------------|
| `DO-XXXX` | `DO-2026-00042` | Module 6 dispatch label | ❌ Hard-coded error "not available" | Remove stub; add `DeliveryOrder::where('number', $raw)` lookup; add `ScanResultType::DispatchOrder` (enum case exists but unused) |
| `PCK-XXXX` | `PCK-2026-00007` | Pick list PDF/label (Module 6) | ❌ Unknown | Add `str_starts_with($raw, 'PCK-')` prefix check; add `ScanResultType::PickList` case |
| `DSP-XXXX` | `DSP-2026-00001` | Dispatch record number | ❌ Unknown | Add `str_starts_with($raw, 'DSP-')` prefix check; add `ScanResultType::Dispatch` case |
| `GP-XXXX` | `GP-2026-00001` | Gate pass QR code (spec §7) | ❌ Unknown | Add `str_starts_with($raw, 'GP-')` prefix check; add `ScanResultType::GatePass` case |
| AIM `]C0`, `]E0`, `]Q0` | `]E00123456789012` | Any scanner in auto-discrimination mode | ❌ AIM prefix leaks into barcode value | Strip all `]XX` AIM identifiers (2-char code after `]`) before prefix matching |
| Trailing CRLF (`\r\n`) | `LPN-0001\r\n` | Zebra RS232 scanners | ❌ `trim()` does not strip `\r\n` before prefix check | Replace `trim()` with `trim($raw, " \t\n\r\0\x0B")` (PHP default trim already does this — verify) |

> **Note:** PHP's `trim()` does strip `\r` and `\n` by default, so CRLF is handled. The only gap is AIM identifiers outside the three already stripped (`]C1`, `]d2`, `]Q3`).

---

## Section 5 — Permission / Scope Gaps

### 5.1 Missing scan permissions per role

| Role | Missing permission | Impact |
|------|-------------------|--------|
| `warehouse-manager` | `scan.receive`, `scan.putaway` | Cannot use scanner receive/putaway; must use desktop only |
| `storekeeper` | `scan.receive`, `scan.putaway` | **Critical** — Storekeeper is the primary receiver/putter-away; cannot use scanner |
| `warehouse-supervisor` | `scan.receive`, `scan.putaway`, `scan.pick`, `scan.dispatch` | Cannot use scanner for any operational workflow |
| `department-user` | `scan.lookup` not present (intentional per spec but worth confirming) | No self-service stock lookup on scanner |
| `scanner-operator` | `scan.gate` not present | Cannot use Gate Check on scanner (gate-security role handles this) |

> The spec (Module 5 prompt) lists: Storekeeper gets "all scan.*". The seeder gives storekeeper only `scan.pick`, `scan.dispatch`, `scan.move`, `scan.lookup`. Storekeeper **cannot use the scanner for receiving or putaway** — a critical operational gap.

### 5.2 Route-level permission mismatches

| Route | Middleware | Gap |
|-------|-----------|-----|
| `/scan` group | `permission:scan.receive|scan.putaway|scan.pick|...` (OR of all) | A user with only `scan.lookup` can enter `/scan/home` but cannot access any other tile — not a bug but could confuse with blank tile list |
| `/scan/stock-lookup` | (inherits scan group) | Maps to `Scan\Lookup`, not `Scan\StockLookup` — permission `scan.stock-lookup` grants access to the wrong component |
| `inbound.grn.tally` | `permission:po.receive` | Only the PO/inbound-orders workflow; storekeeper receiving standard GRNs cannot access the tally page |

### 5.3 VW scoping on scan lookups

- `Scan\Lookup::itemStockData()` queries `StockBalance` without any VW filter — a Department User with `scan.lookup` sees stock of all departments. `ScanContext` holds `$virtualWarehouseId` but is never passed from `Scan\Lookup`.
- `Scan\Lookup::locationData()` similarly shows bin contents from all VWs.
- `BarcodeResolver::resolveMulti()` bypasses global scopes on Item lookup (`withoutGlobalScopes()`) — correct for resolver (items must match regardless of VW), but VW enforcement must happen at the Livewire level.

---

## Section 6 — Proposed Implementation Order

### Phase 1 — Critical fixes (blocker issues, ~2–3 days)

1. **Add `scan.receive` and `scan.putaway` to `storekeeper` role** in `RolePermissionSeeder` and seed. Also add to `warehouse-manager` and `warehouse-supervisor`.
2. **Fix `DO-` resolver** — remove hard-coded error; add `DeliveryOrder::where('number', $raw)` lookup; use the existing `ScanResultType::DispatchOrder` enum case.
3. **Add `PCK-` and `GP-` prefix resolvers** — `PickList` and `GatePass` models; add enum cases `ScanResultType::PickList` and `ScanResultType::GatePass`.
4. **Fix `/scan/stock-lookup` route** — point to `Scan\StockLookup` or redirect to `Scan\Lookup` and remove the stub entirely.
5. **Fix `Scan\Move` hardcoded VW=1** — resolve VW from the stock balance at the source bin before calling `RelocationService::moveItem`.
6. **Implement `window.startCameraScan`** — wire up `BarcodeDetector` API (with ZXing fallback) in `scan.blade.php`.

### Phase 2 — Scanner pick and dispatch (Module 6 scanner completion, ~3–4 days)

7. **Rework `Scan\Pick`** — add step-by-step scan flow per spec: scan list/DO → task list → scan bin → scan item/LPN → qty → serial loop → confirm. All steps call `PickingService::executeTask`. Add `x-scan-input` to each step; use `BarcodeResolver` for bin and item/LPN.
8. **Rework `Scan\Dispatch`** — add DO scan step (using `BarcodeResolver` once DO- is fixed); add per-LPN load-scan loop; vehicle details; confirm. Call `DispatchService`.
9. **Rework `Scan\GateCheck`** — replace raw `<input>` with `x-scan-input`; call `BarcodeResolver` (once GP- is added).

### Phase 3 — Desktop scan consistency (~2 days)

10. **Fix `Inbound/Grn/Receive`** — replace ad-hoc raw input with `x-scan-input`; call `BarcodeResolver`; handle GS1 auto-fill for batch/expiry/qty.
11. **Fix `Outbound/Gate/Check`** — replace `wire:keydown.enter` raw input with `x-scan-input`; call `BarcodeResolver` for GP- once added.
12. **Fix `x-scan-input` Tab key** — add `keydown.tab.prevent` handler mirroring the Enter handler.
13. **Apply VW scoping in `Scan\Lookup`** — pass `virtualWarehouseId` from user's accessible VWs to filter item stock and location contents.

### Phase 4 — High-value desktop scan additions (~3–4 days)

14. **`Inventory/Counts/Show`** — add `x-scan-input` above the lines table; `BarcodeResolver` → `ScanResultType::Item` → find matching count line → focus its qty input and auto-trigger `saveEntry`. (Highest operational value — blind count on mobile browser.)
15. **`Outbound/PickLists/Index`** — add `x-scan-input`; resolve PCK- or DO number → open pick list → first open task.
16. **`Outbound/Dispatch/Index`** dispatch modal — add LPN scan loop for load-check per spec.
17. **`Warehouse/Movements/Create`** and **`Inventory/Adjustments/Create`** — add per-line item barcode scan.

### Phase 5 — Nice-to-have scan shortcuts (~1–2 days)

18. `Inventory/Serials/Lookup` — serial scan to pre-fill search.
19. `Inventory/Quarantine/Index` — LPN/item scan to open release modal.
20. `Inventory/SkuTracker/Index` — item barcode scan to `selectItem()`.
21. `Admin/Locations/Index` — location code/barcode scan to jump-to row.
22. `Outbound/Orders/Create` and Edit — item scan to add/fill line.

### Phase 6 — Infrastructure hardening (ongoing)

23. **Global keystroke capture** — add a global Alpine `@keydown.window` listener that redirects keystrokes to the active `[data-scan-input]` when focus is elsewhere; guard with timing heuristic (>5 chars in <200 ms is a scan).
24. **Device field in `StockLedger::move()`** — add optional `?string $device = null` parameter; thread through `RelocationService`, `PickingService`, `DispatchService` from `session('scan_device_code')`.
25. **Scan audit log** — add a `scan_events` table (or Spatie activity log entry): user_id, device_code, route, raw_barcode, resolved_type, resolved_id, success, error_message, created_at.
26. **Offline queue** — debounce failed Livewire calls; show retry button on scan error when offline.
27. **`ScannerDeviceService::nextDeviceCode()`** — replace string `max()` with `SELECT MAX(CAST(SUBSTRING(code,5) AS UNSIGNED))` to avoid alphabetic ordering issues above SCN-099.

---

## Section 7 — Open Questions

1. **Should `warehouse-manager` be able to scan receive/putaway on a handheld?** The spec says yes (they have all operations access), but the seeder currently excludes them. Confirm before seeder fix.

2. **`Scan\Receive` bypasses `GrnWorkflow::addReceipt()`** — it writes `GrnReceipt` directly. Should it call the same workflow method as the desktop receive screen, or is the mobile receive intentionally lightweight? This affects whether variance threshold and manager-notification logic applies on scanner.

3. **Scan/Putaway scan flow mismatch** — the spec says "scan LPN → show contents → scan bin → confirm". The current implementation scans item first, then finds the task. Does the spec take precedence (LPN first) or is item-first acceptable for the desktop storekeeper?

4. **GRN Tally (`inbound.grn.tally`) is behind `permission:po.receive`** but standard GRN receiving is `grn.receive`. Are they intentionally different screens for PO vs container GRNs? Should tally also accept `grn.receive`?

5. **`Scan\StockLookup`** — should it be a distinct screen from `Scan\Lookup`, or should Lookup serve both purposes? The spec only mentions "Lookup" as a workflow; the two routes seem to be a duplication artefact from an earlier iteration.

6. **`scan.stock-lookup` permission** assigned to `scanner-operator` and `storekeeper` but the route points to `Scan\Lookup` (which requires `scan.lookup`). Do you want to keep the separate permission, or merge it and assign `scan.lookup` to scanner-operator?

7. **Global keystroke capture** — if implemented, should it be desktop-only (the scan layout already auto-focuses), or on the scan layout too (for when a camera fallback is dismissed and focus is lost)?

8. **Camera scan library** — the spec says `BarcodeDetector` API (Chrome/Android) with `@zxing/browser` fallback. `@zxing/browser` is not in `package.json`. Should it be added in this sprint or deferred to a later phase?

9. **Scan audit log** — should raw barcode values be stored verbatim (potential PII/sensitivity for serial numbers)? Should they be hashed for audit purposes?

10. **LPN scan at putaway** — when scanning an LPN on the putaway screen, should the system auto-suggest a bin even if the LPN is not associated with an open putaway task (e.g., a manually moved pallet)?
