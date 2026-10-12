# Module 02 — Stock Ledger: Manual Testing Checklist

**Module scope:** StockLedger, StockReservations, StockQueryService, opening balances, moves, adjustments, reversals, VW isolation, and the `wms:stock:verify` command.

---

## Prerequisites

- [ ] Run migrations: `php artisan migrate:fresh --seed`
- [ ] Seed has completed without errors (`FoundationDemoSeeder` + `StockDemoSeeder`)
- [ ] At least one `system-admin` user exists (davidhaule3@gmail.com)
- [ ] At least one `warehouse-staff` user exists in a restricted VW

---

## 1. Post Opening Balances via the UI Form

- [ ] Log in as `system-admin`
- [ ] Navigate to **Inventory > Adjustments**
- [ ] Select **Opening Balance** as the movement type
- [ ] Choose any active item, location, and virtual warehouse
- [ ] Enter a positive quantity and submit
- [ ] Verify a `StockTransaction` record of type `opening_balance` is created in the DB
- [ ] Verify a `StockMovement` record with the correct `qty` is linked to the transaction
- [ ] Verify `StockBalance.qty_on_hand` incremented by the entered amount (converted to base UoM)

---

## 2. Verify Stock on Hand Math

- [ ] Open **Inventory > Stock on Hand** list for an item that has been received
- [ ] Confirm displayed columns:
  - `On Hand` = sum of all `stock_movements.qty` for that key
  - `Allocated` = sum of active `stock_reservations.qty` for that balance
  - `Available` = On Hand − Allocated
- [ ] Create a reservation via `StockReservations::reserve()` (or through UI if available)
- [ ] Refresh the Stock on Hand page and confirm `Available` decreased by the reserved qty
- [ ] Release the reservation and confirm `Available` is restored

---

## 3. View Movement History and Transaction Details

- [ ] Navigate to **Inventory > Movements** (or transaction history)
- [ ] Confirm all movement types appear in the list: `receipt`, `opening_balance`, `relocation`, etc.
- [ ] Click on a transaction row to open the detail view
- [ ] Confirm the detail view shows:
  - Transaction number (STK-XXXXX)
  - Type, reference, posted_at, posted_by
  - All linked movement lines with item, location, VW, batch, qty
- [ ] Verify the detail view does not expose an Edit or Delete button (immutable records)

---

## 4. Reverse a Transaction (System Admin only)

- [ ] Log in as `system-admin`
- [ ] Find a `receipt` or `opening_balance` transaction in the list
- [ ] Click **Reverse** and enter a reason
- [ ] Confirm a new `reversal` transaction is created
- [ ] Confirm `stock_transactions.reversed_by_transaction_id` is set on the original
- [ ] Confirm `stock_transactions.reverses_transaction_id` is set on the reversal
- [ ] Confirm the affected `StockBalance.qty_on_hand` decreased by the reversed amount
- [ ] Attempt to reverse the same transaction again — confirm an error is shown ("already reversed")
- [ ] Log in as `warehouse-staff` and confirm the Reverse button is not visible or returns 403

---

## 5. Check VW Isolation by Role

- [ ] Create two users: one assigned to VW-A only, one assigned to VW-B only
- [ ] Log in as VW-A user and navigate to Stock on Hand
- [ ] Confirm only stock belonging to VW-A is visible
- [ ] Log in as VW-B user and confirm only VW-B stock is visible
- [ ] Log in as `system-admin` and confirm stock from all VWs is visible
- [ ] Confirm no cross-VW stock is accessible or modifiable by restricted users

---

## 6. Run `php artisan wms:stock:verify`

- [ ] Run the command on a healthy database: `php artisan wms:stock:verify`
- [ ] Confirm output: `All stock balances are correct.` and exit code 0
- [ ] Manually corrupt a balance row:
  ```sql
  UPDATE stock_balances SET qty_on_hand = 9999.0000 WHERE id = 1;
  ```
- [ ] Re-run the verify command
- [ ] Confirm exit code 1 and output contains `mismatch` with the balance ID
- [ ] Run `php artisan wms:stock:verify --fix`
- [ ] Confirm exit code 0 and balance is restored to the correct value
- [ ] Re-run without `--fix` to confirm the database is now clean

---

## 7. Items with Stock Cannot Be Deleted

- [ ] Find an item that has at least one `StockMovement` (`hasTransactions() === true`)
- [ ] Navigate to the item detail page as `system-admin`
- [ ] Attempt to delete the item
- [ ] Confirm the UI shows an error or the Delete button is disabled
- [ ] Confirm the item still exists in the database
- [ ] Find an item with no movements and confirm it can be soft-deleted normally

---

## 8. Quarantine Location Forces On Hold Status

- [ ] Navigate to **Inventory > Adjustments** and select an active quarantine location (type = `quarantine`)
- [ ] Enter a receipt with `status = available`
- [ ] Submit the posting
- [ ] Confirm the resulting `StockBalance.stock_status` is `on_hold` (not `available`)
- [ ] Confirm the `StockMovement.stock_status` is also `on_hold`
- [ ] Verify that stock in a quarantine location does not appear in the available-to-promise calculation

---

## 9. Batch and Expiry Tracking

- [ ] Select an item with `track_batch = true` and attempt to post without specifying a batch number
- [ ] Confirm the UI or service returns a validation error
- [ ] Select an item with `track_expiry = true`, provide a batch number but no expiry date
- [ ] Confirm an error is returned ("requires expiry tracking")
- [ ] Post successfully with both batch number and expiry date
- [ ] Confirm a `Batch` record is created with the correct `expiry_date`
- [ ] Navigate to the near-expiry report and confirm the batch seeded within 30 days appears

---

## 10. UoM Conversion

- [ ] Find an item that has a CTN (carton) UoM with `factor_to_base = 24` (e.g. `DRY-001`)
- [ ] Post a receipt of 2 CTN
- [ ] Confirm `StockBalance.qty_on_hand = 48.0000` (base UoM = PCS)
- [ ] Confirm `StockMovement.entered_qty = 2.0000` and `entered_uom` = CTN
- [ ] Confirm `StockMovement.qty = 48.0000` (converted to base)

---

## 11. Idempotency

- [ ] Trigger the same receipt twice using the same `idempotency_key` (e.g., via an API or test helper)
- [ ] Confirm only one `StockTransaction` and one `StockMovement` are created
- [ ] Confirm `StockBalance.qty_on_hand` only increased once

---

## Sign-off

| # | Check | Pass | Tester | Date |
|---|-------|------|--------|------|
| 1 | Opening balance UI form | | | |
| 2 | Stock on hand math | | | |
| 3 | Movement history / details | | | |
| 4 | Reversal (admin only) | | | |
| 5 | VW isolation by role | | | |
| 6 | wms:stock:verify command | | | |
| 7 | Items with stock cannot be deleted | | | |
| 8 | Quarantine forces on_hold | | | |
| 9 | Batch and expiry rules | | | |
| 10 | UoM conversion | | | |
| 11 | Idempotency | | | |
