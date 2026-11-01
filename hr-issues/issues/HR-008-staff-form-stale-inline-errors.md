# HR-008 — Staff form keeps stale errors after correction; salary is silently overwritten

- Severity: Low (usability/consistency)
- Area: Staff list → Add/Edit Staff
- Environment: 2026-10-31, super_admin

## Observed behaviour 1 — stale inline errors

1. Open Staff list → **Add Staff**.
2. Press **Save** with the form empty.
3. Inline errors appear for Name, Father Name, Phone, Government ID, Email, Education, Position, Address.
4. Fill all of those fields correctly.
5. The errors remain displayed under every field until Save is pressed again.

Expected: an error clears when its field becomes valid (or at minimum on blur/input), so the user can see what is still wrong without re-submitting.

Actual: all errors persist while the values are visibly correct, which reads as "still invalid". Only a second Save re-runs validation.

## Observed behaviour 2 — salary silently changes with position

1. Type `50000` into **Salary**.
2. Change **Position** to `Counselor`.
3. The salary field changes to `60000` (the position's default) without a message.

Expected: either the salary stays as entered, or a visible note says the position default replaced it. Silently changing a pay figure is especially sensitive in payroll software.

## Root cause (likely)

- `validateStaffForm()` writes `formErrors.*`; there is no watcher or input handler clearing a field's error.
- The position watcher assigns `positionSalaryMap[position]` to `selectedStaff.salary` unconditionally.

## Impact

Low individually, but together they reduce trust in the staff form and can produce a wrong base salary that later drives proration, absence deductions and payslips.

## Suggested direction

Clear a field error on input/blur, and only apply the position default when the salary is empty or explicitly confirmed.

## Evidence

- Live run: after filling every required field, the inline message list still contained `Name is required | Father name is required | Contact number is required | Government ID is required | Email is required | Education level is required | Position is required`.
- Salary changed from 50000 to 60000 after selecting Counselor; created employee persisted `60000.00`.
