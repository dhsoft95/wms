# Build Conventions

Every module must follow these rules. Deviations require explicit justification.

## Architecture

- **Thin Livewire components.** Validate input, call the service or action, show the result. No raw queries or business rules inside Livewire components.
- **Business logic** lives in `app/Services/<Module>/` (multi-step orchestration, side effects) or `app/Actions/<Module>/` (single-purpose, synchronous).
- **All multi-table writes** run inside `DB::transaction()`.
- Livewire components do not use `DB::` directly.

## Data types

- **Quantities** are stored as `decimal(18,4)`. Never use `float`.
- **Status fields** use backed PHP enums with explicit allowed transitions via `canTransitionTo()`. Invalid transitions throw `App\Exceptions\InvalidTransitionException`.

## Master data

- Referenced master data is **never hard-deleted** — only deactivated (`soft deletes` + `is_active`).
- A record with dependents cannot be deleted; the check lives in its policy's `delete()` method.

## Document numbers

- Numbers come **only** from `App\Services\Foundation\DocumentNumber::next(string $type)`.
- No component, controller or service may generate document numbers directly.

## Virtual warehouse scoping

- Every model that carries a VW owner must `use App\Models\Concerns\BelongsToVirtualWarehouse`.
- For dropdowns and lists, filter via `VirtualWarehouse::scopeAccessibleBy(User $user)` — not the trait (the VW model itself has no `virtual_warehouse_id`).
- Roles that see all VWs are listed in `config('wms.vw_unrestricted_roles')`. Check there, never hardcode.

## Stock integrity — ledger rule

- **Stock quantities change only through `App\Services\Inventory\StockLedger`.**
- No controller, Livewire component, action, or other service may insert, update, or delete stock movement or stock balance rows directly.
- Ledger rows are **never edited or deleted**. Corrections are new movements.

## Audit

- All master and document models include `use Spatie\Activitylog\Traits\LogsActivity` logging old and new values.

## Permissions

- Every model with business meaning has a policy.
- Permissions follow `module.action` (e.g. `items.create`, `locations.block`).

## No debug code

- No `dd()`, `dump()`, or `ray()` in `app/`.

## Tests

- Every business rule in the spec gets at least one Pest test.
- Each module ends with a manual test checklist at `docs/testing/module-XX.md`.
