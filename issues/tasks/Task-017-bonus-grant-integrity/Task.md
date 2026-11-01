# Task Task-017 — Make bonus grants non-zero, explicit and acknowledged

## Metadata
- Task ID: `Task-017`
- Title: `Make bonus grants non-zero, explicit and acknowledged`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with payroll review`
- Estimated size: `M`
- Source plan/version: `hr-issues/issues/HR-001-bonus-grant-zero-amount.md`; `hr-issues/README.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `HR-001`

## Objective
A granted bonus is the amount the approver saw, is never zero by accident, produces a clear success or error message, and reaches the payslip for that month.

## Context and problem
The demo database row 1 (employee 4, October 2026) is still `is_granted = 1, selected_amount = 0, granted_amount = 0`, which reproduces the report. `bonuslist.vue::confirmGrantBonus()` sends only `{ isGranted: true }`, ignores the failure path with `console.error`, and emits no toast. `WorthyEmployeeController::update()` validates `selectedAmount` with `min:0` and copies whatever `selected_amount` is into `granted_amount`; a zero grant is accepted and then becomes immutable (`business.granted_bonus_immutable`).

The report's proposed field-name mismatch is not accurate: `WorthyEmployeeResource` already returns `selectedAmount`, and the dialog binds it. The real defect is that a zero stored amount can be granted, and the UI neither sends nor confirms an explicit amount or reports the result.

## Scope
### Included
- Show the amount that will actually be granted in the confirmation, and block confirmation with a specific message when it is zero or missing.
- Send the explicit amount in the grant payload rather than only `isGranted`.
- Reject a zero or negative grant server-side with a field-level error; keep the existing immutability rule after a successful grant.
- On success, update the row and toast the employee, month and granted amount; on failure, toast the specific business/validation message.
- Verify the granted amount is the one `SalaryController::availableGrantedBonusesQuery` sums for that employee and month, including the currency snapshot.
- Add a regression test for a zero nomination, a valid grant, a repeat grant and a payroll-sum check.

### Excluded
- Bonus rule calculations, automatic nomination thresholds or calculation snapshots.
- Changing the bonus-to-salary attachment model or currency conversion.
- Redesigning the bonus list page or its filters.
- Global API toast ownership (Task-018; this task follows its contract).

## Preconditions and assumptions
- `worthy_employees.selected_amount` is the nominated amount; `granted_amount` is the amount paid. A grant should not invent an amount different from what the approver sees.
- Assumption to validate: a zero nomination is legitimate to create as a pending nomination but never legitimate to grant. The UI and server enforce that distinction.
- The bonus page is permission-gated by `grant_bonuses`; the grant action must remain hidden without it.
- Payroll runs for the same employee/month; the test uses an isolated month and a disposable employee.

## Dependencies
- `Task-018` — one owner for API error toasts; this task adds success feedback and specific inline errors without duplicating the global failure toast.

## Contracts and boundaries
- Input/output: `PATCH /worthy-employees/{id}` accepts an explicit `grantedAmount` or `selectedAmount` on grant; existing edit payloads keep working. The resource keeps `selectedAmount`/`grantedAmount`.
- Compatibility guarantee: already-granted non-zero records are untouched; the immutability rule remains.
- Error behavior: zero/negative grant returns HTTP 422 with a field-level `selectedAmount` or `grantedAmount` error; a second grant returns the existing immutable error.
- Permission/data scope: `grant_bonuses` and `edit_bonus_nominations` remain separate; no new permission.
- Integration seam: the amount shown in the dialog, sent in the payload and persisted must be the same value; use one normalized field.
- Files this task must not modify: `app/Http/Controllers/SalaryController.php`, `resources/ts/pages/payrolls.vue`, `resources/ts/api/axios.ts`.

## Exclusive file ownership
- `resources/ts/pages/bonuslist.vue` — confirmation amount, payload, toasts and row update.
- `resources/ts/api/bonuslist.ts` — grant payload and response types.
- `app/Http/Controllers/WorthyEmployeeController.php` — zero-grant rejection and explicit amount handling.
- `app/Http/Resources/WorthyEmployeeResource.php` — granted/selected amount and currency fields.
- `tests/Feature/BonusGrantTest.php` — new grant/payroll regression test.

## Acceptance criteria
- [ ] Given a nomination with a zero selected amount, when the user opens Grant, then the confirmation shows zero and refuses to confirm with a specific message instead of writing a zero grant.
- [ ] Given a nomination with a positive amount, when the user confirms, then the dialog displays that amount, the payload includes it explicitly, and the persisted `granted_amount` equals it.
- [ ] Given a zero or negative grant submitted directly to the API, when it is validated, then it is rejected with a field-level error and no row is modified.
- [ ] Given a successful grant, when the response returns, then a toast names the employee, month and amount and the row updates without a page reload.
- [ ] Given a failed grant, when the request fails, then one specific error toast appears and the dialog stays open so the user can correct the input.
- [ ] Given a granted bonus and a generated payslip for the same employee and month, when payroll runs, then the payslip includes the granted amount and not zero.
- [ ] Given an already-granted bonus, when a second grant or edit is submitted, then the immutable-record error is returned and the stored amount is unchanged.

## Verification plan
- Automated: `php artisan test --filter=BonusGrantTest`; extend the existing bonus/payroll tests; run `npm run typecheck`.
- Manual: use a disposable employee and month; nominate 1000, grant it, inspect the row and payslip; create a zero nomination and confirm it cannot be granted; try a direct API grant with zero.
- Fixtures: one employee, one base currency, one positive nomination and one zero nomination, one salary for the month. Non-production only.
- Regression: verify nomination create/edit/dismiss, calculation snapshots and grant permission behavior; verify the payroll granted-bonus query still returns only granted rows.
- Evidence: DB rows before/after, dialog amount, success/error toast, payslip bonus line, test output.

## Implementation notes
Resolve one grant amount on the server from the explicit payload, falling back to the stored nomination only when the payload omits it, and reject a resolved value that is zero or negative. Return the granted amount and currency snapshot in the response so the row can update without a full reload. The confirmation must render the same value the payload sends. Keep the existing immutable check after a successful grant and provide an edit-before-grant path for legacy zero rows.

## Risks and rollback
- Risk: rejecting zero grants leaves existing zero rows un-grantable. Mitigation: provide an explicit correction path for the demo/legacy zero row (edit before grant) and record it.
- Risk: sending both `selectedAmount` and `grantedAmount` can diverge. Mitigation: one server-side rule that resolves and validates a single amount.
- Data migration: none required; the demo zero row is data, not schema. If legacy zeros must be repaired, do it as a reviewed one-off, not an automatic backfill.
- Stop condition: if a granted bonus can be attached to a salary before the amount is finalized, stop and clarify the lifecycle before changing validation.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
