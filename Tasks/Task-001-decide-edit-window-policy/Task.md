# Task Task-001 — Decide the edit-window scope and override policy

## Metadata
- Task ID: `Task-001`
- Title: `Decide the edit-window scope and override policy`
- Initial status: `Ready`
- Type: `Discovery`
- Priority: `P0`
- Owner role: `Product owner with engineering lead`
- Estimated size: `S`
- Source plan/version: `issuesv2/README.md`, 2026-11-10 audit
- Requirement / behavior IDs: `EW-001`, `EW-002`, `EW-003`, `EW-005`

## Objective
Approve one written policy that defines exactly which records the one-hour edit window governs, which workflows are exempt, and who may override it. Downstream implementation then follows one decision instead of three competing readings.

## Context and problem
The audit confirmed that the 60-minute mechanism works server-side, but its policy is unclear and inconsistent. Every seeded employee editor is either unable to edit employees or also holds `override_edit_window`. The middleware is mounted application-wide and blocks old task edits. Contract update routes bypass the gate because their controllers take scalar ids. The feature cannot be called comprehensive or sane until the product owner chooses the intended scope.

## Scope
### Included
- Choose employee-only, application-wide, or an explicit named list of governed resources.
- Name the exempt workflows and the exact override permission.
- State whether employment and other contract edits are governed.
- Define the expected server rejection, UI state, rollout, and handling of records already older than the window.
- Record the decision, rationale, approver, and date in `decision.md`.

### Excluded
- Any application source, route, seeder, migration, test, or UI change.
- Contract extension integrity, employee form implementation, and regression code; those belong to Task-002, Task-003 and Task-004.

## Preconditions and assumptions
- `issuesv2` is the authoritative evidence set for the current behavior.
- The product owner can decide whether the window is about employee records or all edits; this task must not assume either answer.
- The decision must preserve the existing `business.edit_window_expired` error unless the product owner explicitly changes it.

## Dependencies
- `None` — this decision has no upstream task.
- External blocker: product-owner approval is required before the downstream edit-window implementation tasks can start.

## Contracts and boundaries
- Deliverable contract: `Tasks/Task-001-decide-edit-window-policy/decision.md`.
- The decision must identify the governed route/resource set, the override permission, the exact exemptions, and whether contracts, tasks, comments, subtasks, and finance approvals are affected.
- It must state the UI expectation for an expired record and whether a direct API attempt must still fail.
- No API shape, database schema, permission name, or error code changes are approved by this task; those are implementation choices for downstream tasks.
- Files/modules this task must not modify: all application source, tests, migrations, seeders, routes, configuration, and translations.

## Exclusive file ownership
- `Tasks/Task-001-decide-edit-window-policy/decision.md` — sole written decision record for this policy.

## Acceptance criteria
- [ ] Given the three edit-window findings, when the decision is recorded, then it names the governed resource set and the reason for that choice.
- [ ] Given the chosen policy, when an editor reaches the window boundary, then the decision names the exact exempt workflows, the override permission, and whether contracts are governed.
- [ ] Given the approved decision, when downstream tasks read it, then it states the required server error, UI state, rollout notes, and product-owner sign-off.

## Verification plan
- Automated checks: none; this is a decision-only task and must not change product code.
- Manual review: compare the decision against `issuesv2/README.md` and the EW-001 to EW-005 evidence.
- Required evidence: `decision.md` with approver, date, rationale, governed set, exemptions, override rule, UI rule, and rollout notes.
- Downstream check: Task-002, Task-003 and Task-004 must link to this decision rather than restating an unapproved policy.

## Implementation notes
- Keep the decision short enough to apply directly to route middleware and UI states.
- If the product owner chooses application-wide enforcement, list every exempt workflow explicitly and explain why it is exempt.
- If the product owner chooses employee-only enforcement, state that contract and task edits are intentionally exempt so Task-004 does not enforce a rule that was not approved.

## Risks and rollback
- Risk: an ambiguous decision leaves the same route-by-route inconsistency in place.
- Risk: choosing employee-only silently reverses the earlier request that all edits be gated; the decision must record that trade-off.
- Rollback: no code or data changes, so a revised decision only requires updating `decision.md` and notifying the dependent task owners.

## Handoff checklist
- [ ] Decision record is complete and approved.
- [ ] Governed resources, exemptions, override permission, and contract treatment are explicit.
- [ ] Dependent tasks point to the decision rather than inventing policy.
- [ ] No product files were changed.
