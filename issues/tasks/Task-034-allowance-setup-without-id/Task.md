# Task Task-034 — Decide how to set an allowance on staff without a government ID

## Metadata
- Task ID: `Task-034`
- Title: `Decide how to set an allowance on staff without a government ID`
- Initial status: `Ready`
- Type: `Discovery`
- Priority: `P2`
- Owner role: `HR workflow owner`
- Estimated size: `S`
- Source plan/version: `User UI review 2026-11-07; live Edit Staff dialog for a seeded employee`
- Requirement / behavior IDs: `HR-LEAVE-ALLOWANCE-SETUP`

## Objective
An approved decision and an implementation follow-up exist for setting an off-day allowance on an existing employee who has no government ID, without forcing the operator to invent identity data.

## Context and problem
As the seeded super admin in `/hr`, opening **Edit Staff** for Ahmad Karimi and setting **Off-day allowance (days / year)** to 20 then clicking Save showed the inline error **"Government ID is required"** and kept the dialog open. The employee record was seeded with a null `id_number`; the allowance (20) persisted only after a synthetic ID was entered. The allowance setup step is therefore blocked by unrelated identity completeness, and the operator has no explanation of which validation layer requires the ID or how to proceed.

## Scope
### Included
- Identify every layer that blocks the save for an existing employee with no government ID: the staff form validation, the frontend request schema, the backend employee update request and the seeded employee data.
- Compare the options: seed complete identity data for demo staff, require the ID only when creating an employee, or allow a partial update of the allowance for an existing record.
- Record the chosen option with its data, API and permission impact, and create a follow-up implementation task or an explicit rejection with reason.

### Excluded
- Implementing the chosen change; this is a discovery/decision task and must not edit product files.
- Changing allowance semantics, approval math or payroll behavior.
- Backfilling real employee identity data or changing production records.

## Preconditions and assumptions
- The review used the local demo database and seeded staff only; no production data is involved.
- The employee update flow must preserve the existing identity and account rules; the decision cannot silently drop required data validation without an explicit product choice.
- `resources/ts/pages/staff_list.vue` validates the form before saving, while `resources/ts/utils/apiValidation.ts` and the backend request rules may impose separate requirements.

## Dependencies
- `None` — independent discovery task; the approval ledger and the employee date picker are separate surfaces.

## Contracts and boundaries
- Input/output: the decision may affect `PUT /employees/{id}` validation and the staff form, but this task defines no API shape change by itself.
- Compatibility guarantee: existing employees with complete identity data must keep saving without new requirements; existing seeded records must remain visible.
- Error and failure behavior: the chosen option must explain the block in the UI, not only on Save, and must not fabricate identity data silently.
- Permission/data-scope expectations: `edit_employees` remains the write gate; self-profile editing must not gain identity or allowance rights.
- Integration seams: the staff form, the frontend API schema and the backend update request must agree on which fields are required for create versus update.
- Files/modules this task must not modify: all product files, including `resources/ts/pages/staff_list.vue`, `resources/ts/utils/apiValidation.ts`, `app/Http/Requests`, `database/seeders` and the leave controller/service.

## Exclusive file ownership
- None (discovery/decision task; do not edit product files)

## Acceptance criteria
- [ ] Given an existing staff record with no government ID, when the operator opens Edit Staff and changes only the allowance, then the exact blocking validation layer or layers are documented with file and line evidence.
- [ ] Given those constraints, when the decision is recorded, then it names the chosen option, the rejected alternatives, the data and API impact, and the owner.
- [ ] Given the decision, when the task closes, then a follow-up implementation task exists with file ownership, or the rejection is recorded with a concrete reason.

## Verification plan
- Automated: none required for the decision; use `npm run typecheck` only if a follow-up code change is authorized.
- Manual/UI: reproduce the block in the seeded Edit Staff dialog, then record the exact message and whether the allowance itself persisted.
- Data fixtures / seed and cleanup: local demo employee only; do not edit production records.
- Expected commands/results: source inspection of the staff form, frontend schema, backend request and seeder; a recorded decision with a named follow-up.
- Regression, permissions, accessibility: the decision must preserve `edit_employees`, self-profile boundaries and the required identity fields for new employees.
- Required evidence/artifacts: file and line references, the observed UI message and the follow-up task ID.

## Implementation notes
Start from the staff form's save validation, then follow the same fields through the frontend request contract and the backend update request. Do not assume the server is the only blocker or that the client is the only blocker; the observed dialog error is client-side. Prefer the smallest option that keeps identity data honest and makes allowance configuration reachable for a real HR operator.

## Risks and rollback
- Risks / failure modes: weakening identity validation could admit incomplete records; seeding fake IDs could hide the real workflow gap.
- Data migration/backfill/compatibility: none in this decision task.
- Rollback: the decision is documentation-only; no rollback needed.
- Stop conditions / human approval needed: the product owner must approve any change to required employee identity fields.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely "code complete."
