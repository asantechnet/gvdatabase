# CE-003 — An extension can point at a different employee/applicant than its parent

- **Severity:** High
- **Area:** Employment and other contract extension creation
- **Status:** Source-confirmed; not executed to avoid creating bad data
- **Confidence:** High (source trace)

## Summary

`ContractController::extensionParent()` validates the parent’s contract type, role, status, and cancellation, but never compares the parent’s `employee_id` or `applicant_id` with the values submitted for the new extension. The generic `store()` path uses the submitted party identifiers, so an extension can be linked to a different person than the agreement it claims to extend.

## Expected

- An employment extension must belong to the same employee as its parent.
- A customer/applicant extension should belong to the same applicant (subject to the separate academy identity rule, if that is intentional).
- A mismatch should return a specific business error, not silently create a misleading record.

## Actual

- `extensionParent()` only checks:
  - parent is `primary`
  - not cancelled
  - status `active`/`completed`
  - `contract_type_id` matches the template’s type
  - the actor can manage the parent
- `ContractController::store()` then creates the contract with `employee_id`/`applicant_id` from the validated request payload.
- `documentAttributes()` renders the snapshot and printed content from those submitted IDs.
- No test covers a mismatched employee or applicant.

## Impact

- A printed “extension” can name a different employee than the parent agreement.
- The parent link and the party fields tell contradictory stories, undermining the audit trail.
- Employment contract history can be attached to the wrong person; downstream expiry/reporting views may follow the wrong relationship.
- This is a data-integrity risk even if the UI normally prefills the parent’s party.

## Suggested direction (report only; no code changed)

- In `extensionParent()`, compare the submitted `employee_id`/`applicant_id` with the parent and reject mismatches.
- Decide the academy exception explicitly; `BuiltInContractGenerationTest::test_academy_extension_applies_edited_applicant_identity` currently allows an academy extension to apply edited applicant identity. If that is intentional, scope the party-matching rule to employment and other agreements only.
- Add tests for a mismatched employee on an employment extension and a mismatched applicant where the rule applies.

## Evidence

- `app/Http/Controllers/ContractController.php::extensionParent()` and `store()`.
- `tests/Feature/ContractExtensionTest.php` covers type mismatch, cancelled parent, and extension-as-parent; it does not cover party mismatch.
- No destructive test was run because it would create a misleading contract record.
