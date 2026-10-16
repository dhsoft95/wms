# Module 7: Inventory Control (keep it simple)

Read PROJECT-BRIEF.md, docs/BUILD-CONVENTIONS.md and spec section for Inventory Control in docs/WMS_Functional_Breakdown.md.
All stock changes go through StockLedger only. Reuse existing services, reservations and DocumentNumber. No new stock tables.

## Step 1 - Plan first
List migrations, services, enums, permissions and pages. Wait for my confirmation.

## Scope
1. Stock adjustment (ADJ number)
    - Lines: item, bin location, batch/expiry, serials if tracked, qty +/-, reason code (applies_to = adjustment).
    - Draft -> Submitted -> Approved -> Posted (or Rejected).
    - If value/qty is above the adjustment threshold in settings, it needs warehouse-manager approval. Below threshold it posts on submit.
    - Posting uses StockLedger::adjust(). Serial out -> written_off.
2. Quarantine
    - Move stock to/from a quarantine location (StockLedger::move + changeStatus).
    - Actions: Release to a bin (status available) or Write off (via adjustment with reason).
3. Inter-department transfer (TRF number)
    - Change owner VW of stock without moving location (StockLedger::changeOwner).
    - Requires approval. Reserved stock cannot be transferred.
4. Cycle count (CNT number)
    - Create count for selected bins or items. Blind count (system qty hidden from counter).
    - Counter enters counted qty. Variance creates an adjustment that follows the same approval rule.
    - Bins under an open count cannot be picked or put away into.

## Rules
- Only level = bin locations hold stock.
- Cannot adjust/transfer below available (on hand minus reserved).
- Every document is idempotent on post (e.g. adj:{id}:post).
- VW scope applies to all documents.

## Permissions
adjustments.view|create|approve, transfers.view|create|approve, counts.view|create|count|approve, quarantine.manage. Print the role matrix.

## Tests (Pest)
One test per rule above, plus approval threshold, blind count, count lock on bins, serial status after write-off.

## Deliverable
Short report: files changed, role matrix, test results.
Write docs/testing/module-07.md (short checklist using Tigo data).
Stop when done.
