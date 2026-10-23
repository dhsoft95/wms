# Yas Tanzania test data seeder (5 months)

Do NOT scan the whole project. Replace existing demo/Tigo data in the current seeders with this Yas Tanzania data. No new reset command.
Keep existing users. Keep config data (roles, permissions, UoM, sequences, reason codes, settings keys).
All stock and document data must be created through the existing services (StockLedger, workflows), never direct inserts into stock tables.
Seeder must be idempotent (firstOrCreate / skip if exists). Run with: php artisan db:seed

## 1. Company
Account Configuration: company name "Yas Tanzania", address Dar es Salaam, currency TZS.

## 2. Warehouses and locations
- DAR (Main, Dar es Salaam), ARU (Depot, Arusha), MWZ (Depot, Mwanza).
- Each warehouse: 2 receiving docks, 1 staging, 1 quarantine, 1 hazardous area (2 bins), 1 disposal area, 1 IN-TRANSIT, storage zones A and B with bins.
- DAR: 5 yard slots; storage 2 aisles x 2 racks x 2 levels x 2 bins per zone. Depots: smaller (1 aisle x 2 racks x 2 levels x 2 bins).

## 3. Departments, VWs, users
- Departments + VWs: Yas Retail (YAS-RTL), Yas Network (YAS-NET), Yas Enterprise (YAS-ENT).
- Create one test user per role if missing (password: Password@123, email <role>@yas.test):
  department-user (one per department), warehouse-manager, warehouse-supervisor, storekeeper (2), warehouse-officer, data-analyst, hse-officer, viewer-auditor, procurement-officer.
- Approval chain per department for po, mrn, transfer, disposal: level 1 = a department approver user.
- Category users: assign one user to each category in DAR.

## 4. Partners
Suppliers: Huawei Technologies, ZTE Corporation, Nokia. Shipping line: Maersk. Transporter: FastTruck Logistics. Consignee: Yas Tanzania.

## 5. Items (part number, dimensions, weight, min/max stock)
Categories: Devices, Network Equipment, Power, Accessories.
- RTL-MIFI-4G MiFi 4G Router: serial, PCS + CTN(10), Devices, Retail
- RTL-PHONE-A1 Smartphone A1: serial, PCS + CTN(20), Devices, Retail
- RTL-SIM-PACK SIM Starter Pack: batch, PCS + BOX(100), Accessories, Retail
- NET-ANT-4G 4G Antenna: PCS + CTN(4), Network Equipment, Network
- NET-RRU-01 Radio Unit: serial, PCS, Network Equipment, Network
- NET-BAT-48V 48V Battery: batch + expiry, hazardous, PCS, Power, Network
- NET-CBL-RF1 RF Cable: M + ROLL(100), Accessories, Network
- ENT-RTR-01 Enterprise Router: serial, PCS, Network Equipment, Enterprise
  Allocation rules: FEFO for batteries, FIFO for the rest. Set min/max stock so some items end below minimum.
  Each item has a unit price range used for random PO prices.

## 6. Transactions: 5 months of random activity
- Period: last 5 months up to today. Use Carbon::setTestNow() per day so all documents, movements and received_at have real past dates. Reset after.
- Random but reproducible: fixed seed (mt_srand(2026), Faker seed 2026).
- Config at the top of the seeder: $months = 5, $poPerWeek = 3, $mrnPerWeek = 6, $transfersPerWeek = 2.
- Day 1: opening stock in DAR for all items (some in ARU/MWZ).

Per week (random days, random items, realistic qty):
- POs from random suppliers, container or loose cargo, full workflow to Verified/Closed. About 1 in 8 has a short, excess or damaged line. Random unit prices within the item range.
- MRNs per department, full workflow to Delivered. Qty never more than available (check before creating). About 1 in 10 uses special treatment.
- Transfers (STN): mix of in-warehouse, departmental and DAR -> ARU/MWZ warehouse transfers, completed.
  Per month:
- 1 cycle count with small random variances, adjusted.
- 1-2 stock adjustments (damage, found stock).
- Month 3: one item marked obsolete. Month 4: one disposal completed (auction).

Leave open documents in the last 2 weeks so every status exists today:
- POs: Draft, Pending Approval, Approved (planned, not arrived), Assigned, Partially Received, Completed - Pending Verification, one with an open issue.
- MRNs: Draft, Pending Approval, Allocated, Picking.
- 1 warehouse transfer DAR -> ARU in transit. 1 adjustment pending. 1 open cycle count. 1 disposal pending (destroy).
- 1 container in yard over the dwell warning. 1 batch expiring within 30 days, 1 expired.
- Some items below min stock, some never moved for 90+ days (for aging and slow movers).

While seeding:
- Disable notifications, emails, AI summary calls and queued jobs (Notification::fake / config flag); run jobs synchronously.
- Wrap each day in a transaction; print progress per week.

## 7. Rules for you
- Build it, no plan step.
- After seeding run: php artisan wms:stock:verify (must pass).
- Print totals: POs, GRNs, MRNs, STNs, adjustments, counts, disposals, and the date range of data.
- Report in 10 lines max: counts per module, test user list, verify result. No code dumps.
