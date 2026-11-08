# Task Task-032 — Make normal off-day approval work and show the allowance balance

## Metadata
- Task ID: `Task-032`
- Title: `Make normal off-day approval work and show the allowance balance`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `HR frontend owner`
- Estimated size: `S`
- Source plan/version: `User UI review 2026-11-07; live /leave-requests and /tasks sessions with the seeded super admin and employee accounts`
- Requirement / behavior IDs: `HR-LEAVE-APPROVAL`, `HR-LEAVE-BALANCE`

## Objective
Approving a pending off-day request with the normal Approve Selected action succeeds, and the company Off Day Requests ledger shows each employee's allowance, used and remaining days so an approval visibly reduces the balance.

## Context and problem
The Off Day Requests page at `/leave-requests` is the company approval surface. In the live app, selecting a pending one-day request for Ahmad Karimi and clicking **Approve Selected (1)** produced the client toast **"Force Off Day: Choose a valid option."** and the request stayed pending. The template binds `@click="grantSelected"`, so the click event is passed as the `forceOffDay` argument and the default `false` is bypassed; the request validator then receives a `MouseEvent` for `force_off_day` and rejects the PATCH before it is sent. A direct call to the same grant service, used only to isolate the downstream behavior, approved the row with `off_day_days = 1` and the employee's ledger showed allowance 20, used 1, remaining 19.

The ledger also loads `allowance` and `usage` from `GET /leave-requests`, but only the allowance-exception dialog uses them. The table and KPI strip show no per-employee balance, so an approver cannot see the balance drop on the page the review asked about. Evidence: `evidence/approval-validation-error.png`, `evidence/ledger-after-approval.png`, `evidence/employee-balance.png`.

## Scope
### Included
- Make the normal Approve Selected action call `grantSelected` with `forceOffDay = false` by an explicit call, while keeping the exception action `grantSelected(true)` unchanged.
- Show per-employee allowance, used and remaining days on the Off Day Requests ledger from the existing `allowances` and `usage` response maps, refreshed after a grant.
- Keep the current permission gates and the exception dialog's justification requirement intact.

### Excluded
- Changing the backend grant math, allowance resolution, Friday rule, attendance ledger or approval permissions.
- The employee self-service date picker covered by Task-033.
- Staff-form or seeder changes for missing government IDs covered by Task-034.
- Redesigning the page or adding new API fields.

## Preconditions and assumptions
- The live review used the seeded super admin `admin@example.test` and employee `ahmad.karimi@example.test`; both are non-production demo accounts.
- `GET /leave-requests` already returns `allowances` and `usage` for the visible year; no backend contract change is required for the balance display.
- "Allowance" means the employee's personal `off_day_allowance_days`; "used" means the sum of approved `off_day_days` for the selected year.

## Dependencies
- `None` — independent UI task; the staff-form decision and the self-service picker work are separate surfaces.

## Contracts and boundaries
- Input/output: `PATCH /leave-requests/grant` keeps `{ ids: number[], force_off_day: boolean, approval_note?: string }`; the normal path must send `force_off_day: false`, never a DOM event object.
- Compatibility guarantee: `grantSelected(true)` and the exception dialog keep sending `force_off_day: true` and a required justification; approved rows keep the server-issued `off_day_days`, `off_day_override` and approver.
- Error and failure behavior: a rejected approval keeps the selection and shows the existing API or validation feedback; a success reloads the ledger and clears the selection.
- Permission/data-scope expectations: existing `view_leave_requests` and `grant_leave_requests` gates are unchanged; the balance must only show data already returned to that viewer.
- Integration seams: the page consumes `LeaveRequestApi.listPage`; do not add a second ledger fetch or recompute usage from the visible page.
- Files/modules this task must not modify: `app/Http/Controllers/LeaveRequestController.php`, `app/Services/LeaveRequestService.php`, `resources/ts/components/paid-time-off/MyPaidTimeOffDialog.vue`, `resources/ts/pages/staff_list.vue`.

## Exclusive file ownership
- `resources/ts/pages/leave_requests.vue` — approval handler call shape and per-employee balance presentation.

Forward ownership of this file moved from Task-006 on 2026-11-07; Task-006 no longer edits it.

## Acceptance criteria
- [ ] Given a pending full-day request inside the employee's allowance, when the approver clicks Approve Selected, then the PATCH sends `force_off_day` as boolean false and the row becomes Off day with the approver recorded.
- [ ] Given that approval, when the ledger reloads, then the employee's allowance, used and remaining days are visible on the Off Day Requests page and show the expected drop (for example 20 → used 1 → remaining 19).
- [ ] Given a selection that exceeds the allowance, when the approver uses Approve as paid exception, then the existing justification dialog still appears, requires a note and records the off-day override.
- [ ] Given a viewer without `grant_leave_requests`, when they open the ledger, then no approval action is offered and the read-only balance display still works.

## Verification plan
- Automated: `npm run typecheck` (never bare `vue-tsc` or `tsc`); run any existing frontend test that covers the leave page if one exists.
- Manual/UI: sign in as the seeded super admin, open `/leave-requests`, approve a pending request for an employee with a 20-day allowance, and confirm the toast, row treatment, approver and visible used/remaining change; repeat with a zero-allowance employee to confirm the exception path.
- Data fixtures / seed and cleanup: use the seeded demo employee and one pending request; keep or delete the synthetic request according to the local demo policy.
- Expected commands/results: typecheck exit 0; approval response 200; no validation toast on the normal path.
- Regression, permissions, accessibility: verify the exception path still requires a justification; check the balance presentation at desktop and mobile widths in LTR/RTL and light/dark; keep the table's horizontal scroll affordance.
- Required evidence/artifacts: before/after screenshots of the approval and balance, plus the request payload shape if the browser inspector is used.

## Implementation notes
The root cause is the template handler: use `@click="grantSelected"` only if the handler ignores its argument; otherwise call `grantSelected(false)` explicitly. Keep the exception action as `grantSelected(true)`. Prefer a small per-employee balance cell or keyed lookup from `allowances` and `usage`; do not duplicate the exception dialog's risk calculation. Update the row after `load()` so the displayed usage comes from the server.

## Risks and rollback
- Risks / failure modes: a second click could double-submit if loading is not guarded; the balance map may be empty for an employee outside the current page's year.
- Data migration/backfill/compatibility: none.
- Rollback: revert the page changes; no schema or API change.
- Stop conditions / human approval needed: none beyond the existing approval permission and demo-data policy.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely "code complete."
