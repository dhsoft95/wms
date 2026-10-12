# Pre-Module 1: Virtual warehouse scoping + ledger-first architecture

## Context
- Laravel + Livewire WMS. Auth, users, roles (spatie/laravel-permission), 2FA and the `departments` table are done.
- Functional spec: `docs/WMS_Functional_Breakdown.md` (sections 0.4, 0.5, 5.1, 6). Read it first.
- Read `PROJECT-BRIEF.md` first and update it when done.
- This task is architecture only. Do not build items, locations, partners, containers or stock tables yet.

## Fix 1 - Replace department scoping with virtual warehouse scoping

### Problem
Users are currently scoped by `users.department_id` via `BelongsToDepartment`. The spec requires every user to be assigned to one or more virtual warehouses (VWs), and all data is filtered by those VWs, not by department.

### Step 1 - Inspect and report first
1. List every file using `BelongsToDepartment`, `department_id` scoping, department policies or department checks in Livewire components, policies, middleware, Blade and tests.
2. Propose the migration and change plan.
3. Wait for my confirmation before editing.

### Step 2 - Database
- `departments`: add `head_user_id` (nullable FK users, null on delete) and `report_email` (nullable).
- New `virtual_warehouses`: `id`, `code` (unique), `name`, `department_id` (FK, restrict on delete), `is_active` (default true), timestamps, soft deletes.
- New pivot `user_virtual_warehouse`: `user_id`, `virtual_warehouse_id`, timestamps, unique pair, cascade on user delete.
- Data migration: for every existing department create one VW (`code` = department code, `name` = department name). Attach every user with a `department_id` to that VW.
- Keep `users.department_id` as "primary department" for display only. Nothing uses it for access control after this task.

### Step 3 - Models and scope
- `VirtualWarehouse` model with `department()`, `users()`, `scopeActive()`.
- `User`:
    - `virtualWarehouses()` relation
    - `accessibleVirtualWarehouseIds(): array` (cached per request)
    - `canAccessAllVirtualWarehouses(): bool` (true for `system-admin`, `warehouse-manager` and `viewer-auditor`)
- `app/Models/Concerns/BelongsToVirtualWarehouse.php` trait:
    - Global scope `VirtualWarehouseScope` filtering `virtual_warehouse_id IN (user's VW ids)`.
    - Skipped when there is no authenticated user (console, queue jobs, seeders) or when the user can access all VWs.
    - A user with zero assigned VWs sees nothing (empty result), not everything.
    - `virtualWarehouse()` relation, `scopeForVirtualWarehouse($id)`.
    - On `creating`: if `virtual_warehouse_id` is empty and the user has exactly one VW, fill it. If a restricted user sets a VW they are not assigned to, throw an authorization exception.
- Helper `VirtualWarehouseAccess::ensure(User $user, int $vwId)` for policies and services.
- Remove `BelongsToDepartment` and update every place found in Step 1.

### Step 4 - Policies and UI
- Policies check VW access with `VirtualWarehouseAccess`, not department.
- User create/edit form: replace the single department select with:
    - Primary department (select, display only)
    - Virtual warehouses (multi-select, required for `department-user`, optional for others, hidden for roles that see all)
- Users list shows assigned VWs as badges.
- Departments page: show each department's VWs, allow create/edit/deactivate VWs (`virtual-warehouses.manage`, System Admin only).
- A department or VW with users or data cannot be deleted, only deactivated.
- Topbar: if a user has more than one VW, add a VW switcher (`All my warehouses` + each VW) stored in session. The scope respects the selection but never widens beyond assigned VWs.

### Step 5 - Permissions
Add `virtual-warehouses.view` and `virtual-warehouses.manage`. Update the role matrix and seeders.

## Fix 2 - Ledger-first architecture (rule only, no stock tables yet)

All stock quantity changes must go through one service. Set this up now so no later module writes stock directly.

### Step 6 - Build conventions
Create `docs/BUILD-CONVENTIONS.md` with these rules. Every later module must follow it:
- Business logic in `app/Services/<Module>/` or `app/Actions/<Module>/`. Livewire components stay thin: validate input, call the service, show the result.
- All multi-table writes run inside `DB::transaction()`.
- Quantities are `decimal(18,4)`. Never use float.
- Status fields use backed PHP enums with explicit allowed transitions (`canTransitionTo()`). Invalid transitions throw a domain exception.
- Referenced master data is never hard-deleted, only deactivated (soft deletes plus `is_active`).
- Document numbers come only from the `DocumentNumber` service (built in Module 1).
- Every VW-owned model uses `BelongsToVirtualWarehouse`.
- Every model with business meaning has a policy. Permissions follow `module.action`.
- **Stock ledger rule:** stock quantities change only through `App\Services\Inventory\StockLedger`. No controller, Livewire component, action or other service may insert, update or delete stock movement or stock balance rows directly. Ledger rows are never edited or deleted; corrections are new movements.
- Audit with `spatie/laravel-activitylog` on all master and document models, logging old and new values.
- Every business rule in the spec gets at least one Pest test.
- Each module ends with a manual test checklist at `docs/testing/module-XX.md`.

### Step 7 - Setup
- Install `spatie/laravel-activitylog` and publish its migration.
- Create `app/Services/Inventory/StockLedger.php` as an empty `final` class with a docblock describing its future responsibility. No methods yet.
- Add a Pest architecture test file `tests/Arch/ConventionsTest.php` with rules that apply now:
    - No `dd`, `dump`, `ray` in `app/`.
    - Livewire components do not use `DB::` directly.
    - Models do not depend on Livewire.
    - A skipped placeholder test named `stock tables are only written by StockLedger` with a `// enabled in Module 2` comment.
- Add a pointer to `docs/BUILD-CONVENTIONS.md` at the top of `PROJECT-BRIEF.md`.

## Step 8 - Tests (Pest)
- Department user sees only records in their assigned VWs; another VW's record via direct URL returns 403 or 404.
- User with two VWs sees both; the VW switcher narrows to one and cannot widen beyond assigned.
- User with zero VWs sees nothing.
- System Admin, Warehouse Manager and Viewer/Auditor see all VWs.
- Creating a record with a VW the user is not assigned to is rejected.
- Scope does not apply in console/queue context.
- Data migration: each department got one VW and users were attached correctly.
- Existing auth, role and user tests still pass.

Use a test-only model (e.g. `tests/Fixtures/ScopedRecord` with its own migration created inside the test) to test the trait, since no real VW-owned models exist yet.

## Step 9 - Manual test checklist
Write `docs/testing/pre-module-01.md`: create VWs, assign users, log in as each role and confirm what each one sees, with the expected result for each step.

## Deliverable
- Files changed, especially every replaced department check.
- Updated role/permission matrix.
- Test results summary.

Stop when done. Do not start Module 1.
