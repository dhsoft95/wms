# Module 01 — Foundation Master Data: Manual Test Checklist

> Run after seeding: `php artisan db:seed`  
> App URL: `http://localhost:8000` (or `php artisan serve`)

---

## 1. Authentication & Role Access

| # | Step | Expected |
|---|------|----------|
| 1.1 | Log in as **Admin** (`admin@wms.test`) | Lands on dashboard; full nav visible |
| 1.2 | Log in as **Warehouse Manager** | Nav has Master Data group |
| 1.3 | Log in as **Storekeeper** | Can see Partners in nav; can view Locations |
| 1.4 | Log in as **Scanner Operator** | Can view Items and Locations; no Create/Edit links |
| 1.5 | Log in as **Department User** | Cannot see Locations link; cannot reach `/admin/locations` directly (403) |
| 1.6 | Visit `/admin/skus` while logged in | Redirects to `/admin/items` (301 → 302 → items index) |

---

## 2. Navigation

| # | Step | Expected |
|---|------|----------|
| 2.1 | Expand **Master Data** nav group | Shows: Items, Item Categories, Warehouses, Locations, Partners |
| 2.2 | Expand **Administration** nav group | Shows Settings entry |
| 2.3 | No "SKU Master" entry visible anywhere in nav | Absent |

---

## 3. Item Categories

| # | Step | Expected |
|---|------|----------|
| 3.1 | Go to **Master Data → Item Categories** | Table with seeded categories (Food & Beverage, etc.) |
| 3.2 | Click **New** and create category "Test Cat" | Appears in table |
| 3.3 | Inline edit name → "Test Cat Updated" | Name changes in place |
| 3.4 | Deactivate "Test Cat Updated" | Status badge shows Inactive |
| 3.5 | Attempt to delete a category that has items assigned | Should show error or deactivate only (not hard-delete) |

---

## 4. Units of Measure

| # | Step | Expected |
|---|------|----------|
| 4.1 | Go to **Settings → UoM** tab | Lists seeded UoMs (PCS, CTN, PLT, KG …) |
| 4.2 | Add new UoM "DZ" (Dozen), factor 12 | Appears in list |
| 4.3 | Edit DZ description | Saved successfully |
| 4.4 | Attempt to deactivate PCS (used by seeded items) | Shows "in use" warning; status not changed |

---

## 5. Items — Create

| # | Step | Expected |
|---|------|----------|
| 5.1 | Go to **Master Data → Items → New Item** | Form opens on General tab |
| 5.2 | Submit with blank SKU | Validation error: "SKU is required" |
| 5.3 | Enter duplicate SKU (e.g., "ITEM-001") | Validation: unique including soft-deleted |
| 5.4 | Fill all General fields, Base UoM = PCS, save | Item created; redirects to items index |
| 5.5 | Go back, create same SKU again | Blocked by unique rule |
| 5.6 | On **UoMs tab**: add CTN row, factor = 24, set default purchase | Saves correctly |
| 5.7 | On **Barcodes tab**: add barcode, set UoM = PCS, type = internal, mark primary | Saves; barcode unique across `item_barcodes` |
| 5.8 | Add duplicate barcode on another item | Validation error |
| 5.9 | On **Tracking tab**: enable "Track Expiry" | "Track Batch" auto-checks |
| 5.10 | Uncheck "Track Batch" while expiry is on | Batch re-checks (forced) |
| 5.11 | On **Dimensions tab**: enter length/width/height/weight decimals | Accepts 3 decimal places (e.g., 0.375) |

---

## 6. Items — Show

| # | Step | Expected |
|---|------|----------|
| 6.1 | Click item row to open detail page | Summary card shows SKU, name, category, status |
| 6.2 | UoMs section visible | Lists base UoM + any added UoMs with conversion factors |
| 6.3 | Barcodes section visible | Primary barcode highlighted |
| 6.4 | Tracking flags section | Shows badge for Batch/Expiry/Serial |
| 6.5 | Activity log section | Shows "created" event with timestamp |
| 6.6 | Stock placeholder section present | Displays "No stock movements yet" or similar |

---

## 7. Items — Edit

| # | Step | Expected |
|---|------|----------|
| 7.1 | Open Edit for a seeded item | Form pre-populated with correct values |
| 7.2 | Change name and save | Toast "Item updated successfully" |
| 7.3 | Remove a UoM row and save | UoM row removed from item_uoms |
| 7.4 | Change SKU to one that belongs to a soft-deleted item | Validation blocks it |
| 7.5 | Deactivate item via toggle | Status changes; item still visible in list (soft-delete is separate) |

---

## 8. Warehouses

| # | Step | Expected |
|---|------|----------|
| 8.1 | Go to **Warehouses → New** | Form with code, name, address, status |
| 8.2 | Create warehouse "TEST" | Appears in list |
| 8.3 | Create duplicate code "TEST" | Validation error (unique including soft-deleted) |
| 8.4 | Edit address and save | Toast success |
| 8.5 | Deactivate warehouse that has locations | Shows deactivated; locations unaffected |

---

## 9. Locations — Create (Single)

| # | Step | Expected |
|---|------|----------|
| 9.1 | Go to **Locations → New Location** | Form: warehouse, level, parent, segment code, type |
| 9.2 | Select level = Zone | Parent dropdown empty (zones have no parent) |
| 9.3 | Select level = Aisle, pick warehouse | Parent dropdown shows only Zones in that warehouse |
| 9.4 | Create Zone "A" in MAIN | Code = `MAIN-A`; path = `/` |
| 9.5 | Create Aisle "01" under Zone A | Code = `MAIN-A-01`; path = `/{zone_id}/` |
| 9.6 | Create Bin under Shelf → type = Quarantine | `is_pickable = false` in DB |
| 9.7 | Create Bin → type = Storage | `is_pickable = true` in DB |
| 9.8 | Create duplicate code | Unique validation fires |

---

## 10. Locations — Bulk Generate

| # | Step | Expected |
|---|------|----------|
| 10.1 | Go to **Locations → Bulk Generate** | Form: warehouse, parent, level, type, segment prefix, start, end, padding |
| 10.2 | Set warehouse; parent list updates | Shows locations in selected warehouse |
| 10.3 | Numeric range: start=1, end=5, pad=2 | Preview shows 01, 02, 03, 04, 05 |
| 10.4 | Alpha range: start=A, end=E | Preview shows A, B, C, D, E |
| 10.5 | Existing codes shown with "exists" badge | Pre-existing codes flagged in preview |
| 10.6 | Click **Generate** | Only non-existing codes created; toast shows count |
| 10.7 | Click **Back to Form** on preview step | Returns to input form |

---

## 11. Locations — Edit & Blocking

| # | Step | Expected |
|---|------|----------|
| 11.1 | Edit location: change segment code | Code and all descendant codes rebuilt |
| 11.2 | Edit location: change parent | Path recalculated |
| 11.3 | Block a location (Active → Blocked) | `status=blocked`, `blocked_at` set, `blocked_by` = current user id |
| 11.4 | Unblock it (Blocked → Active) | `status=active`, blocked_* fields cleared |
| 11.5 | Attempt to set Full manually via UI | Not possible; Full is automatic only |
| 11.6 | Try Blocked → Full transition | Should raise InvalidTransitionException (no UI path to trigger) |

---

## 12. Partners

| # | Step | Expected |
|---|------|----------|
| 12.1 | Go to **Partners → New** | Form: code, name, type checkboxes (supplier/customer/3pl), address tabs |
| 12.2 | Create supplier "SUP-001" | Appears in list with Supplier badge |
| 12.3 | Create with duplicate partner code | Validation error (unique incl. soft-deleted) |
| 12.4 | Edit partner → add customer flag | Both Supplier + Customer badges show |
| 12.5 | View partner detail | Address lines, contact info, partner flags shown |
| 12.6 | Storekeeper can view partners | No 403; no Edit/Create buttons |

---

## 13. Settings

| # | Step | Expected |
|---|------|----------|
| 13.1 | Go to **Administration → Settings** | 5 tabs: Company Profile, Operations, Document Sequences, Reason Codes, UoM |
| 13.2 | **Company Profile**: enter company name, upload PNG logo | Logo stored in `storage/app/public/branding`; preview shown |
| 13.3 | Save settings | Toast success; settings cached |
| 13.4 | Reload page | Company name and logo persist |
| 13.5 | **Document Sequences**: verify 7 seeded sequences | GRN, DO, ADJ, TRF, CNT, LPN, CTR present |
| 13.6 | Edit GRN prefix to "RCPT" and save | Next GRN call would use new prefix |
| 13.7 | **Reason Codes**: verify seeded codes present | Categories: adjustment, transfer, return, damage, count_variance |
| 13.8 | Add new reason code | Appears in list |
| 13.9 | **Operations**: toggle any setting and save | Value persists on reload |

---

## 14. Document Number Generation

| # | Step | Expected |
|---|------|----------|
| 14.1 | Run `DocumentNumber::next('grn')` via tinker | Returns formatted string e.g. `GRN-2026-00001` |
| 14.2 | Call next() twice | Second call returns `GRN-2026-00002` |
| 14.3 | Check sequence counter in `document_sequences` | `next_number` incremented correctly |

---

## 15. Role/Permission Matrix Quick-Check

| Role | items.create | locations.view | partners.view | settings.manage |
|------|-------------|---------------|--------------|-----------------|
| Admin | ✓ | ✓ | ✓ | ✓ |
| Warehouse Manager | ✓ | ✓ | ✓ | ✓ |
| Storekeeper | ✗ | ✓ | ✓ | ✗ |
| Scanner Operator | ✗ | ✓ | ✗ | ✗ |
| Department User | ✗ | ✗ | ✗ | ✗ |

> Verify by visiting the relevant routes while logged in as each role. 403 expected where ✗.

---

## 16. Activity Log

| # | Step | Expected |
|---|------|----------|
| 16.1 | Create an item | `activity_log` has event=created, subject_type=Item |
| 16.2 | Edit an item | event=updated, `properties.old` and `properties.attributes` filled |
| 16.3 | Create a location | event=created for Location |
| 16.4 | Block a location | event=updated with status change recorded |

---

## Pass Criteria

- All ✅ in sections 1–16  
- No unhandled exceptions in Laravel log (`storage/logs/laravel.log`)  
- `php artisan test` shows **0 failures**
