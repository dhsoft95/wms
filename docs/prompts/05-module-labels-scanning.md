# Module 5: Barcode Labels and Scanning

## Context
- Laravel + Livewire WMS. Done: Modules 1, 2, 2B, 3, 4 (Receiving + Putaway).
- Read `PROJECT-BRIEF.md` and `docs/BUILD-CONVENTIONS.md` first. Update `PROJECT-BRIEF.md` when done.
- Spec: `docs/WMS_Functional_Breakdown.md` sections 7 (Barcode Label Generation) and 8 (Barcode Scanning).
- Scanner screens must call the existing services (`GrnWorkflow`/receipt service, `PutawayEngine`, `StockLedger`). No duplicated business logic in scanner components.
- Scanner Operator role is already restricted to `/scan/*`. Keep that.
- Use existing UI tokens (brand + semantic only), components and guard tests. Scanner layout is its own minimal mobile-first layout (no sidebar), solid surfaces (no glass blur) for speed and outdoor readability.

## Scope
- Labels: templates, designer, PDF and ZPL output, printers, print log, printing from documents.
- Scanning: barcode resolver (including GS1), scanner app shell, workflows **Receive, Putaway, Move, Lookup**, device registration.
- Pick, Dispatch and Count scanner workflows are added in Modules 6 and 7 using this framework. Show their tiles only when the user has the permission and the feature exists (hidden for now).

## Step 1 - Inspect and plan first
Report existing `/scan` routes and placeholder components, current LPN sheet, and installed barcode packages. Propose tables, services, the resolver rules, label rendering approach, and screens. Wait for my confirmation.

---

# Part A - Labels (spec 7)

## A1. Packages
- `tecnickcom/tc-lib-barcode` for Code128, QR, EAN-13 and DataMatrix (one library for all four). Remove `picqer/php-barcode-generator` if it becomes unused.
- `barryvdh/laravel-dompdf` (or confirm a better option) for PDF labels with exact mm page sizes.

## A2. Tables
- `label_templates`: `name`, `type` (enum: `pallet_lpn`, `item`, `location`, `container`, `dispatch`), `width_mm`, `height_mm`, `dpi` (203 or 300), `elements` (json), `is_default` (one default per type), `is_active`, timestamps, activity log.
- `printers`: `name`, `kind` (`pdf_browser`, `zpl_network`, `zpl_download`), `ip`, `port` (default 9100), `dpi`, `location_note`, `is_active`.
- `label_print_jobs`: `label_template_id`, `type`, `printable_type`, `printable_id`, `copies`, `printer_id`, `output` (`pdf` / `zpl`), `is_reprint`, `reprint_reason_code_id`, `reprint_note`, `printed_by`, `created_at`.

## A3. Template elements (json)
Each element: `id`, `kind` (`text`, `field`, `barcode`, `logo`, `line`, `box`), `x_mm`, `y_mm`, `w_mm`, `h_mm`, plus:
- text/field: `value` or `field_key`, `font_size_pt`, `bold`, `align` (left/center/right), `max_lines`
- barcode: `symbology` (`code128`, `qr`, `ean13`, `datamatrix`), `field_key` it encodes, `show_text`
- line/box: `thickness_mm`

Available fields per label type (spec 7.1): SKU, description, batch, expiry, qty, UoM, LPN, container no., GRN no., department/VW, location code, receive date, company logo, company name, DO number (dispatch, Module 6).

## A4. Template designer (no code needed for admins)
- Canvas drawn to scale (mm grid, 1 mm snap, zoom), label size from width/height.
- Add element from a toolbar; drag to move; resize handles; properties panel on the right (field, font size, bold, align, symbology, size).
- Live **preview** with sample data for the label type, and a "Preview PDF" button.
- Duplicate template, set default per type, deactivate.
- Ship seeded default templates: Pallet/LPN 100×150, Item 50×25, Location 100×50, Container 100×150, Dispatch 100×150.

## A5. Rendering
- `LabelRenderer` service: (template, data rows, copies) → **PDF** (one label per page, exact mm size) or **ZPL**.
- ZPL: convert mm to dots by DPI; text → `^FO`/`^A0N`/`^FD`; Code128 `^BC`, QR `^BQ`, EAN-13 `^BE`, DataMatrix `^BX`; lines/boxes `^GB`; logo converted to monochrome `^GF` (or skipped with a warning if conversion fails).
- `zpl_network` printers: send raw ZPL over TCP to ip:port with a short timeout; on failure show an error and offer "Download ZPL". `zpl_download`: download `.zpl` file. `pdf_browser`: open the PDF for browser printing.
- Unit tests compare generated ZPL for a fixed template to an expected snapshot.

## A6. Printing from documents (spec 7.3)
- **GRN detail**: "Print pallet labels" prints all LPN labels for the GRN in one click; per-LPN print in the LPNs tab. Replaces the A4 LPN sheet as the main option (keep A4 sheet as a fallback).
- **Item detail / Items list**: print item labels on demand (choose qty, batch).
- **Locations**: bulk print bin labels (select rows, or a whole zone/aisle from the tree).
- **Container detail**: print container label (also offered right after Confirm arrival).
- **Dispatch**: template type exists; printing is wired in Module 6.
- Print dialog (modal): template (default preselected), printer, copies, preview thumbnail.

## A7. Reprint control (spec: prevent duplicate labels in circulation)
- First print of an LPN or container label is normal. Any later print of the same LPN/container is a **reprint**: requires `labels.reprint` permission and a reason code (`applies_to = reprint`) plus optional note.
- All prints written to `label_print_jobs`; LPN `print_count`/`printed_at` updated.
- **Label print log** screen: filters (type, user, date, reprints only).

---

# Part B - Scanning (spec 8)

## B1. Barcode resolver
`BarcodeResolver::resolve(string $raw, ?ScanContext $context): ScanResult` returns type + matched record(s) + parsed data:
- Order of checks (configurable in Settings → Scanning): LPN prefix (`LPN-`), container internal no. (`CTR-`) and ISO container number, GRN (`GRN-`), DO (`DO-`), location code / location barcode, item barcode (`item_barcodes`), SKU, serial number.
- **GS1 support**: parse GS1-128 / GS1 DataMatrix with AIs `(01)` GTIN, `(10)` batch, `(17)` expiry YYMMDD, `(21)` serial, `(30)`/`(37)` qty; handle FNC1/GS separators. GTIN matches item barcodes. Parsed batch, expiry, serial and qty pre-fill the current step.
- Ambiguous result (e.g. same value is a serial and a barcode): use the context (what the current step expects) first; otherwise show a picker.
- Unknown barcode → clear error result.
- Must respond in under 300 ms for a normal lookup (indexes in place).

## B2. Scanner app shell (`/scan`)
- Mobile-first, portrait, works on Android rugged devices (Zebra, Honeywell) and phones. Large touch targets (min 56 px), large fonts, high contrast, minimal typing.
- **Home**: tiles for permitted workflows (Receive, Putaway, Move, Lookup); user name and device name; logout.
- **One scan input** per step, always auto-focused (re-focus after every action and when the page regains focus) so a keyboard-wedge scanner works without tapping. Enter key submits.
- **Camera scanning fallback**: button opens the camera (use the browser `BarcodeDetector` API where available, otherwise `@zxing/browser`), supports Code128, QR, EAN-13, DataMatrix.
- **Feedback** on every scan: full-screen green flash + short high beep + short vibration for success; red flash + low double beep + long vibration + big message for errors. Sound can be muted per device.
- Numeric keypad for quantities (big buttons, +/− steppers, "use expected qty" button).
- Step progress shown at top ("Step 2 of 4: Scan bin"); Back and Cancel always visible.
- Connection lost banner; actions disabled while offline (no offline queue in this module).

## B3. Device registration
- `scanner_devices`: `code` (e.g. `SCN-001`), `name`, `device_uid` (random id stored on the device in a cookie + localStorage), `last_user_id`, `last_seen_at`, `is_active`.
- First visit to `/scan` on a new device auto-registers it (pending name); admins can rename or deactivate in Administration → Scanner Devices. Deactivated devices can't use `/scan`.
- Every stock posting from the scanner sets the ledger `device` field to the device code (spec 5.1).

## B4. Scan workflows (this module)
All workflows run every validation from spec 8 with clear messages: unknown barcode, wrong item for this task, wrong location, item belongs to another department (VW), qty exceeds expected/allocated (warning or block per setting).

1. **Receive** (spec: GRN → item/pallet → confirm qty):
   Scan or choose GRN (list of GRNs in `receiving` for this warehouse) → scan item (GS1 auto-fills batch/expiry/serial/qty) → qty (keypad) → batch/expiry if needed → condition (Good / Damaged) → pallet: scan existing LPN or "New pallet" (prints LPN label to the device's default printer if set) → serial loop for serial items (scan each, live count, duplicate/in-stock rejected) → save receipt. Uses Module 4 receipt service; finishing and confirming the GRN stays on desktop.
2. **Putaway** (LPN → destination bin):
   Scan LPN (or pick from "My tasks") → show contents and **suggested bin** in large text → scan bin → validate (active, not full/blocked, capacity, storage type) → confirm → task done. Uses `PutawayEngine` and the putaway task completion service.
3. **Move** (spec 4.2 internal relocation: source bin → item/LPN → destination bin):
   Scan source bin → scan LPN (moves whole pallet) or item → qty (+ batch choice if several, + serial scans for serial items) → scan destination bin → confirm → `StockLedger::move()` with movement type `relocation`. Ownership (VW) never changes. Put this in a `RelocationService` so Module 7's desktop screen reuses it.
4. **Lookup** (any barcode → what and where):
    - Item: stock by location, batch and VW (available / allocated / on hold).
    - Bin: contents with LPNs.
    - LPN: contents, current location, GRN.
    - Serial: item, status, location, short history.
    - Container: status, yard slot, dwell days.
      VW scope applies (department users only see their own stock).

## B5. Settings → Scanning
Resolver order and prefixes; excess qty behaviour (`warn` / `block`); sound on/off default; default label printer per scanner device.

---

## Permissions
`labels.templates.manage`, `labels.print`, `labels.reprint`, `labels.log.view`, `printers.manage`, `scanner-devices.manage`, `scan.receive`, `scan.putaway`, `scan.move`, `scan.lookup`.

| Role | Access |
|---|---|
| System Admin | All |
| Warehouse Manager | All |
| Storekeeper | labels.print, labels.log.view, all `scan.*` |
| Scanner Operator | all `scan.*`, labels.print (from scanner only) |
| Department User | scan.lookup only if they use a device (default: none) |
| Viewer / Auditor | labels.log.view |

Register desktop pages in `config/navigation.php` (Administration: Label Templates, Printers, Scanner Devices; Reports & Documents: Label Print Log). Print the matrix.

## Seeder
Default templates (A4), one `pdf_browser` printer and one `zpl_download` printer, two scanner devices.

## Tests (Pest)
- Resolver: each type, prefixes, ISO container number, location code, item barcode, SKU, serial, ambiguity by context, unknown.
- GS1 parsing: GTIN/batch/expiry/serial/qty with and without separators; expiry YYMMDD conversion.
- Label rendering: PDF page size matches template mm; ZPL snapshot; each symbology renders; mm→dots conversion at 203 and 300 dpi.
- Reprint: first print free, second requires permission and reason; print log written; LPN print_count updated.
- Network printer failure handled with fallback.
- Scanner Receive: receipt saved through Module 4 service, serial loop rules, VW mismatch rejected, excess qty warn vs block.
- Scanner Putaway: wrong bin, full bin, blocked bin rejected; correct bin completes the task and moves stock.
- Scanner Move: whole LPN move, partial item move, serial move, VW unchanged, ledger device code set.
- Lookup results VW-scoped.
- Scanner Operator can only access `/scan/*`; deactivated device blocked.
- `wms:stock:verify` clean after scanner flows.
- Guard tests (no raw tables, no off-brand colours) still pass.

## Manual test checklist
Write `docs/testing/module-05.md`: design a template, print PDF and ZPL, reprint with reason, bulk bin labels; on a phone: register device, Receive with keyboard scanner and camera, GS1 barcode, serials, Putaway to suggested and wrong bin, Move pallet and partial qty, Lookup each barcode type.

## Deliverable
Tables, services, resolver rules, label rendering, scanner screens, permission matrix, files changed, test results (`php artisan test` 0 failures, `mysql` group, `npm run build`), decisions taken.

Stop when done. Do not start Module 6.
