# Task Task-006 — Unify responsible-employee assignment

## Metadata
- Task ID: `Task-006`
- Title: `Unify responsible-employee assignment`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P0`
- Owner role: `Laravel and Vue case-access implementer`
- Estimated size: `L`
- Source plan/version: `commission-issues/LOGIC-20261110-003/issue.md`, 2026-11-10 review
- Requirement / behavior IDs: `LOGIC-20261110-003`

## Objective
Make the plural responsible-employee assignment the source of truth for case display, filtering, and access, while keeping the legacy singular field as a compatibility mirror. Assigned admission employees should see and be attributed to their cases without being given Manager or company-wide access.

## Context and problem
The super-admin Edit case form writes employee ids to `applicant_responsible_employees`, but the Applications list and case-access scope read `applicants.responsible_employee_id`. As a result, assigned cases still show Unassigned and the admission employee cannot see them without an extra role. The commission engine already uses the plural relation, so the visibility and attribution layers are the remaining split.

## Scope
### Included
- Treat `applicant_responsible_employees` as the authoritative assignment set for case display, filters, and access.
- Maintain `applicants.responsible_employee_id` as a primary compatibility mirror when the assignment is written.
- Update `ApplicantController` list, detail, and access checks to recognize every responsible employee, not only the singular field.
- Update `ContractAccess` case/contract visibility to use the same assignment set.
- Update the Applications employee column to show assigned employees instead of Unassigned.
- Preserve legacy records that only have the singular field populated.
- Add regression tests for single, multiple, removed, and legacy assignments.

### Excluded
- Recomputing commission amounts; the responsible-employee commission fix is already complete and must remain unchanged.
- Changing the `assign_responsible_employees` permission or the super-admin assignment workflow.
- Contract extension behavior and the Academy commission path.
- Broadly granting `view_all_applicants` or Manager to admission users.

## Preconditions and assumptions
- The plural pivot is the intended multi-assignee model; the singular column remains for compatibility and existing readers.
- A case may have multiple responsible employees; no single employee should be silently dropped from access because they are not first in the list.
- Existing commission attribution uses the plural pivot and must not be duplicated or changed by this task.

## Dependencies
- `None` — no upstream task is required; the commission-side fix is already delivered.

## Contracts and boundaries
- Data contract: plural pivot is authoritative; `responsible_employee_id` is a derived primary mirror, not the only source for access.
- Access contract: an employee in the pivot may view a case under the existing case-scope rules; permission gates such as `view_applicants` still apply.
- List contract: the Applications employee cell and `responsible_employee`/`responsible_employees` payloads agree for assigned cases.
- Filter contract: `responsible_employee_id` filtering includes cases where that employee appears in the pivot.
- Compatibility: a legacy singular-only record remains visible to its employee.
- Files/modules this task must not modify: `app/Http/Controllers/ContractController.php`, contract extension components, `app/Services/CommissionService.php`, and Academy commission screens.

## Exclusive file ownership
- `app/Services/Contracts/ContractGenerationService.php` — write the pivot and compatibility mirror together.
- `app/Http/Controllers/ApplicantController.php` — list, filter, detail, and access checks.
- `app/Support/ContractAccess.php` — contract visibility through the assignment set.
- `app/Http/Resources/ApplicantResource.php` — consistent assignment fields.
- `resources/ts/pages/applicants.vue` — employee column and assignment display.
- `tests/Feature/ResponsibleEmployeeAssignmentTest.php` — assignment write-path regressions.
- `tests/Feature/ResponsibleEmployeeVisibilityTest.php` — access, list, filter, and legacy cases.

## Acceptance criteria
- [ ] Given a super admin assigns one responsible employee through the plural assignment field, when the Applications list loads, then the row names that employee instead of Unassigned.
- [ ] Given that assigned employee signs in without Manager or view_all_applicants, when they open Applications and the case, then the case is visible.
- [ ] Given multiple responsible employees on one case, when each signs in, then all assigned employees pass the case access scope and the row remains attributed.
- [ ] Given a legacy case with only responsible_employee_id populated and an empty pivot, when it is listed and viewed, then the legacy assignment still works.
- [ ] Given contract visibility and commission attribution, when a case has plural responsibilities, then contract access uses the same assignment set and existing commission regression tests pass.
- [ ] Given the updated assignment write path, when responsibility is changed, then the singular field is maintained as a primary compatibility mirror without dropping additional pivot members.

## Verification plan
- Automated checks: `php artisan test tests/Feature/ResponsibleEmployeeAssignmentTest.php tests/Feature/ResponsibleEmployeeVisibilityTest.php tests/Feature/CommissionResponsibilityRegressionTest.php`; `npm run typecheck`.
- Manual/UI scenario: assign Test Admission to a case as super admin, sign in as that admission user without Manager, open Applications and the case, and confirm attribution and visibility.
- Expected result: the case is visible and attributed; removing the assignment removes access; a second responsible employee also has access.
- Regression: legacy singular-only case remains visible; seller/responsible commission ledger is unchanged.
- Required evidence: test output, Applications row after assignment, admission user list/detail before and after access, and a database check of both assignment fields.

## Implementation notes
- Prefer query scopes that use `whereHas('responsibleEmployees')` plus a legacy singular fallback rather than copying the pivot into every caller.
- When writing assignments, choose a deterministic primary for the singular mirror and document the rule; do not remove employees from the pivot to satisfy the singular column.
- Keep permission checks in front of the assignment scope; this task must not turn case assignment into company-wide visibility.
- Do not change `CommissionService`; it already consumes the plural set.

## Risks and rollback
- Risk: using the pivot for access broadens visibility to every assigned employee; that is intended, but each employee must still hold `view_applicants`.
- Risk: a legacy singular-only case may become invisible if the new scope requires a pivot row; keep the fallback and test it.
- Risk: dual writes can drift; centralize the write path and add a reconciliation test.
- Rollback: restore the singular-only access scope and remove the UI display change; do not delete pivot assignment rows.

## Handoff checklist
- [ ] Assigned employees see and are named on their cases without an extra role.
- [ ] Multiple assignees, removal, and legacy singular cases are covered.
- [ ] Contract access and commission regressions pass.
- [ ] No broad permission or Manager role was added as a workaround.
