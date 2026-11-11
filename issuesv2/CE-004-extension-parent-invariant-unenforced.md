# CE-004 — `contract_role = extension` can exist with no parent; no model or DB invariant

- **Severity:** Medium
- **Area:** Contract schema/model integrity, extension data
- **Status:** Confirmed in the current database; source-confirmed
- **Confidence:** High

## Summary

The contract schema allows `contract_role = 'extension'` with `parent_contract_id = NULL`, and neither the model nor the database enforces that an extension has a parent. The current dev database contains three such rows, and the UI renders them as “Extension” with no origin.

This is the structural counterpart to CE-001: the edit-save bug creates the orphans, and the missing invariant lets them persist unnoticed.

## Expected

- `contract_role = extension` should always have a non-null `parent_contract_id`.
- A primary contract should always have a null parent.
- The invariant should be enforced in at least the model/creation path; a database check is preferable for audit integrity.

## Actual

- `contracts.parent_contract_id` is nullable and `contract_role` is a free string with a default of `primary`.
- `Contract::isExtension()` checks only the role, so an orphan is still treated as an extension.
- `ContractResource` reports `parent_contract_id`/`parent_contract_number` as nullable; the list shows the Extension chip with no parent.
- Current orphan rows:

| ID | Number | Type | Role | Status | Parent |
| --- | --- | --- | --- | --- | --- |
| 21 | CTR-26-021 | Employment | extension | draft | `NULL` |
| 23 | CTR-26-023 | Other | extension | cancelled | `NULL` |
| 25 | CTR-26-025 | Other | extension | active | `NULL` |

- Existing review `other-contracts-issues/EXT-20261110-001` explains how an unchanged edit clears the parent; the rows above are the resulting shape.

## Impact

- Orphan extensions are indistinguishable from legitimate extensions in the list and in downstream code that trusts `contract_role`.
- Printed documents can no longer name the source agreement.
- Reporting/expiry logic that follows the role alone may treat an orphan as a current renewal.
- No automatic repair path exists after the parent is lost.

## Suggested direction (report only; no code changed)

- Add a model event/observer or creation guard that rejects `contract_role = extension` without a parent and `primary` with a parent.
- Add a database check constraint where supported (or a validation in all write paths, including factories/seeders).
- Repair the existing orphan rows: relink where the original parent can be determined, otherwise mark them explicitly.
- Add a regression test for the invariant and for the edit-preserves-parent path (CE-001).

## Evidence

- Database query on 2026-11-10: contracts 21, 23, 25 have `contract_role=extension` and `parent_contract_id=NULL`.
- `app/Models/Contract.php` — `isExtension()` uses only the role; `parent()` uses `withTrashed()`.
- `resources/ts/pages/contract_list.vue` — Extension chip renders on role alone; parent number is optional.
