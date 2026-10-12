# Pre-Module 01 — Manual Test Checklist
## Virtual Warehouse Scoping & Conventions

Run `php artisan migrate` and seed with `php artisan db:seed --class=RolePermissionSeeder` + `php artisan db:seed --class=DepartmentSeeder` before starting.

---

### 1. Departments & Virtual Warehouses (Admin)

Login as **system-admin**.

- [ ] Navigate to **Admin → Departments**
- [ ] Confirm each seeded department has a **VWs** button in the Actions column
- [ ] Click **VWs** on a department — the VW management panel should expand below
- [ ] Create a new VW: enter a name and code (e.g. "General Store" / "GS") and click **Add VW**
  - Expected: VW appears in the list, VW count badge on the department row increments
- [ ] Edit the VW: click the cog icon, change the name, click **Update**
  - Expected: VW name updates in the list
- [ ] Deactivate the VW: click the toggle icon — status badge changes to **Inactive**
- [ ] Re-activate it: click toggle again — status badge returns to **Active**
- [ ] Try to delete a VW that has users assigned
  - Expected: no delete button shown (only shown when `users_count === 0`)
- [ ] Delete a VW with zero users: click trash, confirm
  - Expected: VW removed from list
- [ ] Click **VWs** on the same department again — panel collapses

---

### 2. User Management — Assign Virtual Warehouses

Login as **system-admin**.

- [ ] Navigate to **Admin → Users → New User**
- [ ] Select role **Department User** — a **Primary Department** dropdown and **Virtual Warehouses** multi-select appear
- [ ] Select a department; confirm the VW multi-select populates with that department's active VWs
- [ ] Select one or more VWs and submit
  - Expected: user created, VW badges visible in the user list
- [ ] Edit the user: change VW selection, save
  - Expected: user's VW assignment updates, `last_modified` toast shown

---

### 3. VW Scoping — Department User

- [ ] Log in as a **department-user** assigned to exactly one VW ("VW-A")
  - (Create test records in VW-A and VW-B via Tinker or the admin panel before logging in)
- [ ] Confirm the user can only see records belonging to VW-A
- [ ] No VW switcher is visible in the topbar (single-VW users don't see it)

- [ ] Log in as a **department-user** assigned to two VWs ("VW-A" and "VW-B")
- [ ] Confirm both VWs' records are visible
- [ ] The **VW switcher** appears in the topbar
- [ ] Select "VW-A" in the switcher — page reloads, only VW-A records shown
- [ ] Select "All my warehouses" — both VWs' records shown again
- [ ] Manually craft a URL to a record in a VW the user is NOT assigned to
  - Expected: 403 or 404

---

### 4. VW Scoping — Unrestricted Roles

For each role below, log in and confirm **all** virtual warehouses' records are visible:

- [ ] **System Admin** — sees everything, no VW switcher
- [ ] **Warehouse Manager** — sees everything, no VW switcher
- [ ] **Viewer / Auditor** — sees everything, no VW switcher
- [ ] **Storekeeper** — sees everything, no VW switcher

---

### 5. Scanner Operator Access

- [ ] Log in as **scanner-operator**
  - Expected: redirected to `/scan` (scan portal), NOT the main dashboard
- [ ] Try navigating directly to `/dashboard`
  - Expected: 403 Forbidden

---

### 6. VW Switcher — Not Shown for Single-VW Users

- [ ] Log in as a department-user with exactly one VW
  - Expected: VW switcher dropdown is **not visible** in the topbar

---

### 7. Departments — Deletion Rules

Login as **system-admin**.

- [ ] Try to delete a department that has active users
  - Expected: department is **deactivated** (not deleted), toast says "Department has users — deactivated instead."
- [ ] Restore a soft-deleted department: click **Restore** in the table
  - Expected: department restored and set to active

---

### 8. VW Deletion Rules

- [ ] Assign a user to a VW
- [ ] Navigate to the VW list for that department
  - Expected: no delete button for the VW with the assigned user
- [ ] Unassign the user (via edit user)
  - Expected: delete button now appears on the VW row

---

### Rollback Check

- [ ] Run `php artisan migrate:rollback` — confirm it completes without errors
- [ ] Run `php artisan migrate` again — confirm clean re-run
