# Module 03 — Container Management: Manual Test Checklist

## Pre-requisites
- [ ] System Admin user created
- [ ] At least 3 active Partners (1 shipping_line, 1 shipper, 1 consignee)
- [ ] At least 6 active yard_slot Locations (e.g. SLOT-A01 through SLOT-A06)
- [ ] At least 1 Virtual Warehouse (VW) configured
- [ ] At least 2 roles: `system-admin` (unrestricted) and `department-user` (VW-scoped)

---

## 1. Container Registration

### 1.1 ISO 6346 Validation
- [ ] Navigate to Inbound → Containers → Register Container
- [ ] Enter `MSCU4134997` — live indicator shows green "Valid ISO 6346 number"
- [ ] Enter `MSCU4134990` — live indicator shows red "Invalid check digit"
- [ ] Enter `MSKU1234567` — indicator shows invalid (bad check digit)
- [ ] Check "Override ISO 6346 validation" toggle appears when invalid number is entered
- [ ] Ticking override without a reason shows validation error on save
- [ ] Ticking override with a reason allows saving with non-standard number

### 1.2 Required Fields
- [ ] Attempt to save with no Container No — form validation fires
- [ ] Attempt to save with no Size Type — form validation fires
- [ ] Save with all required fields only (no BL, no partners) — succeeds, redirects to Show page

### 1.3 Partner Dropdowns
- [ ] Shipping Line dropdown shows only partners with `type = shipping_line`
- [ ] Shipper dropdown shows only partners with `type = shipper`
- [ ] Consignee dropdown shows only partners with `type = consignee`

### 1.4 Post-Save
- [ ] Toast "Container CTR-YYYY-XXXX registered" appears
- [ ] Redirected to Container Show page
- [ ] Container appears in index with status badge "Expected"
- [ ] Internal number (CTR-YYYY-XXXX) appears in list

---

## 2. Container List

### 2.1 Filters
- [ ] Status tabs show correct counts (All, Expected, Arrived, etc.)
- [ ] Clicking "Arrived" tab filters list to arrived containers only
- [ ] Search by container_no (partial match works)
- [ ] Search by BL number
- [ ] Search by vessel name
- [ ] "Warning dwell" filter returns only containers with dwell ≥ 7 days
- [ ] "Critical dwell" filter returns only containers with dwell ≥ 14 days
- [ ] Wire.poll.30s refreshes the page automatically

### 2.2 Columns
- [ ] Internal # (CTR-YYYY-XXXX) shown in mono
- [ ] Container no in bold mono, size type underneath
- [ ] BL no in mono (or — if blank)
- [ ] Vessel and voyage (V.XX) shown
- [ ] ETA and Arrived dates formatted as "dd MMM YYYY"
- [ ] Yard slot shown as code chip (or — if unassigned)
- [ ] Dwell badge: neutral (< 7d), amber (7–13d), red (≥ 14d) 
- [ ] Status badge with color dot

### 2.3 Row Actions
- [ ] Eye icon always shows and navigates to Show page
- [ ] Pencil icon shown only for Expected/Arrived/Parked and with update permission
- [ ] Pencil icon hidden for Released/Cancelled/Unloading containers

### 2.4 Empty State
- [ ] With no matching data, empty state with cube icon and helpful text appears (not raw <table> empty row)

---

## 3. Container Show Page

### 3.1 Header
- [ ] Container no shown in large mono font
- [ ] Status badge shown
- [ ] Dwell badge shown if arrived_at is set
- [ ] Internal number and size type shown in subtitle
- [ ] Back link to Containers list

### 3.2 Action Buttons (state-dependent)
- [ ] **Expected**: "Confirm Arrival" and "Edit" and "Cancel" buttons visible
- [ ] **Arrived**: "Assign Yard Slot" and "Start Unloading" and "Edit" visible
- [ ] **Parked**: "Move Slot", "Start Unloading", "Edit" visible
- [ ] **Unloading**: "Mark as Unloaded" button visible
- [ ] **Unloaded**: "Release Container" button visible
- [ ] **Released/Cancelled**: no action buttons

### 3.3 Tabs
- [ ] Overview | Expected Lines | Documents | Discrepancies | History | Received & Variance all present
- [ ] Tab click switches content
- [ ] Discrepancies tab shows red count badge when there are open discrepancies

### 3.4 Overview Tab
- [ ] Container No, Seal No, Arrival Seal, Seal verification indicator shown
- [ ] Shipping details: BL, vessel, voyage, shipping line
- [ ] Partners: shipper, consignee
- [ ] Schedule: ETA, arrived_at, free days, dwell days badge, demurrage days
- [ ] Yard Slot: code chip or "Not assigned"
- [ ] Notes section visible when notes are present

---

## 4. Status Workflow

### 4.1 Confirm Arrival
- [ ] Open Confirm Arrival modal
- [ ] Enter arrival date/time and seal number
- [ ] When arrival seal matches manifest seal: live indicator shows green "Match"
- [ ] When seals differ: indicator shows red "Mismatch — discrepancy will be created"
- [ ] Confirm — status changes to Arrived
- [ ] Status history row added in History tab
- [ ] Seal mismatch discrepancy appears in Discrepancies tab with type "Seal Mismatch"

### 4.2 Assign Yard Slot (Park)
- [ ] Open Assign Yard Slot modal
- [ ] Free slot dropdown shows only slots not occupied by active containers
- [ ] Select slot and confirm — status changes to Parked
- [ ] Yard slot chip appears in header and Overview tab
- [ ] Slot no longer appears as free in Yard Map

### 4.3 Move Slot
- [ ] Move Slot modal requires new slot selection + reason
- [ ] After move: yard_moves record created, History tab shows move entry
- [ ] Old slot freed, new slot occupied

### 4.4 Start Unloading
- [ ] With zero expected lines: error toast "no expected lines"
- [ ] Add lines and try again: status transitions to Unloading
- [ ] Start Unloading button is gone after transition

### 4.5 Mark as Unloaded
- [ ] Default gate blocks unloading if Module 4 (receiving) gate returns error
- [ ] Error message shown in toast

### 4.6 Release
- [ ] Release modal requires released_at datetime and released_to partner
- [ ] After release: status = Released, yard_location_id = null, slot is freed
- [ ] Released At shown in Overview tab

### 4.7 Cancel
- [ ] Can only cancel Expected containers (Arrived will fail with error toast)
- [ ] Reason must be minimum 10 characters
- [ ] After cancel: status = Cancelled, yard_location_id = null

---

## 5. Expected Lines

### 5.1 Add Line
- [ ] Add Line button visible only when container is Expected/Arrived/Parked
- [ ] Modal fields: Raw SKU, Map to Item, Description, Qty (required), UOM, Batch, Expiry, Weight, VW (required), Notes
- [ ] Qty validation fires if left empty
- [ ] VW validation fires if not selected
- [ ] Line saved, line_no auto-assigned (max + 1)
- [ ] Totals by VW section updates

### 5.2 Edit Line
- [ ] Edit pencil icon on line row
- [ ] Modal pre-filled with existing values
- [ ] Save updates line

### 5.3 Delete Line
- [ ] Delete (X) button with wire:confirm
- [ ] Line removed, totals update

### 5.4 Mapped Indicator
- [ ] Lines with item_id show green check in Mapped column
- [ ] Lines without item_id show dash

### 5.5 Locked Lines
- [ ] Lines tab buttons are hidden for Unloading/Unloaded/Released/Cancelled containers

---

## 6. Documents

### 6.1 Upload
- [ ] Upload Document modal: file input and type select
- [ ] Large file (>20MB) shows validation error
- [ ] After upload: document appears in list with type, name, size, uploader, date
- [ ] Toast "Document uploaded"

### 6.2 Download
- [ ] Download icon links to `/containers/{id}/documents/{doc}/download`
- [ ] File downloads with correct original filename and MIME type
- [ ] Accessing another container's document returns 404

### 6.3 Delete
- [ ] Delete button visible with `containers.documents.delete` permission
- [ ] After delete: document removed from list, file removed from storage

---

## 7. Discrepancies

### 7.1 Add
- [ ] Add Discrepancy button requires `addDiscrepancy` policy
- [ ] All types selectable: Seal Mismatch, Short, Excess, Damaged, Unexpected Item, Other
- [ ] Description minimum 5 characters enforced
- [ ] Discrepancy appears in list with "Open" badge

### 7.2 Resolve
- [ ] Resolve button visible to users with `resolveDiscrepancy` policy
- [ ] Clicking Resolve updates resolved_at, resolved_by; badge changes to "Resolved"
- [ ] Discrepancies tab badge count decrements

---

## 8. History Tab

- [ ] All status changes appear with from → to status, user, and timestamp
- [ ] Yard moves appear with from/to location, reason, user, timestamp
- [ ] Timeline sorted newest first

---

## 9. Yard Map

### 9.1 Grid
- [ ] All active yard_slot locations rendered as grid tiles
- [ ] Free slots: dashed green border
- [ ] Occupied (normal dwell): brand-tinted tile showing container_no and size
- [ ] Occupied (warning dwell): amber border/bg
- [ ] Occupied (critical dwell): red border/bg
- [ ] Dwell day count shown on tile

### 9.2 Filters
- [ ] Zone filter shows distinct zones (first two code segments)
- [ ] Selecting a zone filters to that zone's slots only
- [ ] "Show free slots only" toggle hides occupied tiles

### 9.3 Side Panel
- [ ] Click an occupied slot: side panel slides in with container details
- [ ] Side panel shows: container_no, number, status, yard slot, dwell, size, shipping line, line count
- [ ] "View Container" link navigates to Show page
- [ ] Click free slot: side panel shows "Free slot" message
- [ ] Click selected slot again: deselects (panel closes)

### 9.4 Stats
- [ ] Total Slots, Occupied, Free, Utilization% KPI cards at top
- [ ] Utilization turns amber at 70%, red at 90%

---

## 10. Dwell Dashboard

- [ ] 5 KPI cards: In Yard, Avg Dwell, Over Warning, Over Critical, Total Demurrage
- [ ] Overwarning count turns amber when > 0
- [ ] Critical count turns red when > 0
- [ ] Table sorted by dwell descending (longest first)
- [ ] Dwell badge colors match: neutral/amber/red
- [ ] Demurrage days shown in red when > 0, dash when 0
- [ ] View icon links to container show page
- [ ] Empty state when no containers have arrived_at

---

## 11. Dashboard Integration

- [ ] "Active Containers" KPI card shows real count from database
- [ ] "arriving this week" trend text shows real count of expected containers with ETA within the week
- [ ] Inbound Pipeline table shows up to 5 real containers (expected/arrived/parked/unloading)
- [ ] Container numbers are links to Show page
- [ ] "No active containers" message when pipeline is empty
- [ ] Status badges use correct colors per ContainerStatus

---

## 12. Notification Bell

- [ ] Bell icon in top-right navbar area
- [ ] Red dot appears when there are unread notifications
- [ ] Clicking bell opens dropdown with unread notifications
- [ ] Each notification shows title, message, and relative time
- [ ] Clicking a notification with container_id navigates to that container and marks it read
- [ ] "Mark all read" button clears all and closes dropdown
- [ ] Dropdown closes when clicking outside
- [ ] "No unread notifications" state when all read

---

## 13. Data Import Page — Card 7 & 8

- [ ] Navigate to Administration → Data Import
- [ ] Card 7 "Container Packing List" appears
- [ ] Card 8 "Active Containers" appears
- [ ] Upload button disabled/hidden for Container Packing List (tooltip: "Upload via container show page")
- [ ] Template download button disabled for both types (tooltip: "Template not available")
- [ ] Active Containers card has working Upload button (goes through DataImport flow)
- [ ] Attempting template download for these types returns 404

---

## 14. Permissions (Role-Based)

### System Admin
- [ ] Can see all containers regardless of VW assignment
- [ ] Can confirm arrival, park, start unloading, release, cancel

### Department User
- [ ] Can only see containers that have expected lines assigned to their VW(s)
- [ ] A container with no expected lines in their VW is invisible (404 on direct URL)

### Read-Only User
- [ ] Can view containers and Show page
- [ ] Register Container button not shown (no `containers.create` permission)
- [ ] Edit link not shown in list/show

---

## 15. Edge Cases

- [ ] Two containers with same container_no: only one can be "active" at a time (unique constraint)
- [ ] Attempting to park in an already-occupied slot shows error toast (not exception)
- [ ] Attempting Start Unloading with unmapped lines (item_id = null) shows error toast
- [ ] Archive test: released container with same container_no as new expected container — both visible to admins
