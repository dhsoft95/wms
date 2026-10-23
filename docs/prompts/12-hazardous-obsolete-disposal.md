# Hazardous, Obsolete and Disposal (simple)

Read PROJECT-BRIEF.md and docs/BUILD-CONVENTIONS.md first.
All stock changes through StockLedger (changeStatus, move, adjust). Reuse the approval chain, DocumentNumber, notifications, activity log.
Step 1: short plan, wait for my confirmation.

## 1. Hazardous
- Item: is_hazardous (checkbox), hazard_note (optional text), SDS file (optional, pdf).
- Location type: add `hazardous`.
- Put-away: hazardous items only into hazardous bins; normal items cannot go into hazardous bins.
- Show a hazard badge on item, GRN, pick list and delivery note.

## 2. Obsolete
- New stock status: obsolete (not pickable, not allocatable).
- Action on Stock on Hand: "Mark obsolete" (select stock, qty, reason) -> needs approval (department chain + warehouse-manager) -> StockLedger::changeStatus.
- Action "Restore to available" (same approval).

## 3. Disposal (DSP document, add sequence)
- Location type: add `disposal` (physical area for items leaving the warehouse).
- Create disposal request from obsolete, damaged or quarantine stock: lines (item, bin, batch, serials, qty), method: Auction or Destroy, reason.
- Flow: Draft -> Submitted -> Approved (department chain + warehouse-manager) -> Moved to disposal area -> Completed. Rejected with reason ends it.
- On approve: storekeeper moves stock to a disposal location (StockLedger::move), status stays not pickable.
- Complete:
    - Auction: buyer name, sale price, date, receipt file.
    - Destroy: method (scrap/recycle/return to supplier), date, witness name, certificate file.
    - Stock leaves the system through StockLedger (out movement type disposal). Serials -> disposed. Record value = qty x unit cost if price exists.
- Printable Disposal Note (Print / Download).

## 4. Permissions
- disposals.create: department-user. disposals.approve: chain + warehouse-manager. disposals.execute: storekeeper, warehouse-officer. obsolete.mark: department-user, warehouse-manager.
- Add Disposal to New Order? No. Put "Disposal" under Inventory in the sidebar.

## 5. Tests (filter Disposal)
- Hazardous item blocked from normal bin and vice versa
- Obsolete stock cannot be allocated or picked; restore works
- Disposal flow: approve -> move -> complete removes stock once (retry no double post), serials disposed
- Reject does not move stock
  Run only filtered tests. Short report.
