# HR-001 — Granting a bonus confirms "Final Amount: 0.00" and persists a zero bonus

- Severity: Critical
- Area: Bonus list (`/bonuslist`) → Grant
- Environment: 2026-10-31, super_admin, seeded data

## Requirement

A granted bonus must be the amount the nominator/approver saw and approved, and it must be the amount that later reaches the employee's payslip. The confirmation step must be a genuine confirmation of a non-zero amount.

## Steps to reproduce

1. Open HR → Bonus list.
2. On the first eligible row (Ahmad Karimi, Performance, October 2026), click **Grant**.
3. Observe the confirmation dialog: `Confirm Bonus Grant / You are about to grant a Performance bonus to Ahmad Karimi for October 2026. / Final Amount: / 0.00 / Cancel / Confirm & Grant`.
4. Click **Confirm & Grant**.
5. Observe: no toast, no visible change to the row, and the dialog closes.
6. Inspect `worthy_employees`: row 1 changed to `is_granted = 1`, `selected_amount = 0`, `granted_amount = 0`.

## Expected

- The confirmation shows the intended amount (the nominated/suggested value).
- Granting persists that amount and returns a success message; a zero or missing amount is rejected with a specific message.
- The granted amount is what payroll attaches to the October payslip.

## Actual

- The confirmation shows `0.00` even though the bonus row carries a Performance nomination for the current month.
- Confirming writes a zero-value grant (`granted_amount = 0`) and produces no feedback at all.
- Because payroll sums granted bonuses by employee and month (`SalaryController::availableGrantedBonusesQuery`), this grant contributes nothing to the October payslip — silently.

## Impact

- A bonus can be "granted" and acknowledged with no money attached; the approving manager has no way to know it was zero.
- The record becomes immutable after granting (`business.granted_bonus_immutable`), so the mistake cannot be corrected through the normal edit path.
- This breaks the bonus → payroll → payslip chain the task explicitly required testing.

## Likely location

- `resources/ts/pages/bonuslist.vue`: `confirmGrantBonus` sends `{ isGranted: true }` only, and the dialog binds `selectedForGrant.selectedAmount`; the API DTO uses snake_case (`selected_amount`) after mapping. The mismatch makes the displayed value undefined → 0.
- The same file swallows failures with `console.error` and never calls `useToast`, so both success and failure are invisible.
- `app/Http/Controllers/WorthyEmployeeController.php` lines 74–91 trusts the stored `selected_amount`; it does not reject a grant of zero.

## Suggested direction (report only, no code changed)

Bind the dialog and payload to the same normalized field, display the value that will be granted, reject zero/negative grants server-side, and emit a success toast containing employee, month and amount plus an error toast on failure.

## Evidence

- Dialog text and absence of toast captured live; persisted row inspected after confirmation:
  `1|emp4|granted:1|sel:0|grant:0|month:2026-10-01`.
- No screenshot was taken of the transient dialog; the text above is the exact rendered copy.
