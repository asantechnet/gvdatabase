# CE-002 — No guard against extending a primary that already has a live renewal

- **Severity:** High
- **Area:** Employment/other contract extension creation
- **Status:** Source-confirmed; not executed as a destructive duplicate
- **Confidence:** High

## Summary

Nothing prevents a user from creating another extension from a primary contract that already has a live extension. The frontend only checks the parent’s role/status/cancellation; the backend checks the parent’s contract type, role, status, and cancellation. Neither checks whether a non-cancelled child already exists.

The translation strings `business.contract_renewal_exists` and `business.contract_not_renewable` are defined in all three languages but are referenced nowhere in the codebase, which strongly suggests the guard was planned but never wired.

## Expected

- A primary agreement should have at most one current extension/renewal.
- If an extension already exists, the UI should direct the user to review it instead of offering another **Extend contract** action.
- The API should reject a second live extension with a specific business code.

## Actual

- `resources/ts/pages/contract_list.vue::canExtend()` checks only:
  `create_contracts` permission, not cancelled, role not extension, status in `active`/`completed`.
- `app/Http/Controllers/ContractController.php::extensionParent()` checks only:
  parent role `primary`, not cancelled, status in `active`/`completed`, and matching `contract_type_id`.
- Neither queries existing children.
- The existing active primary `CTR-26-022` already had an active extension row (`CTR-26-025`, though that row is itself orphaned). The UI still offered **Extend contract**, and creating another extension from 22 succeeded (`CTR-26-027`, draft, parent 22).
- Multiple active agreements for the same primary can therefore coexist.

## Impact

- Duplicate renewal drafts and, after activation, multiple active agreements for one primary.
- Ambiguous “current” agreement for downstream consumers (expiry alerts, reporting, payroll/commission links).
- The unused error strings confirm the intended business rule is not enforced.
- Orphan data becomes harder to distinguish from intentional renewals.

## Suggested direction (report only; no code changed)

- Add a child query in `extensionParent()`: reject when a non-cancelled extension already exists for the parent.
- Mirror the same condition in `canExtend()` so the action is not offered.
- Use `business.contract_renewal_exists` / `business.contract_not_renewable` for the rejection messages.
- Define the lifecycle explicitly: draft extension → activate → parent remains active/expired; whether the parent is marked renewed is a product decision.
- Add tests for a second extension attempt, a cancelled child, and a completed primary.

## Evidence

- `resources/ts/pages/contract_list.vue` — `canExtend()` around line 368.
- `app/Http/Controllers/ContractController.php` — `extensionParent()` around lines 197–215.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — the two unused business strings.
- UI/DB: primary `CTR-26-022` (active) + extension `CTR-26-025` (active, orphaned); extension `CTR-26-027` was created from 22 during this audit and then deleted.
