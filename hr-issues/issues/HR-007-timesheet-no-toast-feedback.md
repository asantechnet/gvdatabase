# HR-007 — Timesheet attendance actions give no toast feedback

- Severity: Medium
- Area: Timesheet (`/timesheet`) → Log attendance
- Environment: 2026-10-31, super_admin

## Steps to reproduce

1. Open HR → Timesheet.
2. Choose an employee in **Log attendance**.
3. Click **Present**.
4. Observe the toast area and the page.

## Expected

- Success: a toast naming the employee and date, e.g. "Attendance recorded for Zahra Aziz on 2026-10-31".
- Failure (duplicate, Friday, future date, before join date): the specific business reason in a toast, plus field-level guidance where possible.

## Actual

- No toast is emitted for either outcome.
- Success is only implied by the ledger refreshing; failure is written to a page-level `error` ref (or silently swallowed) with no consistent alert placement.
- The button pair is disabled until an employee is chosen, so the "missing employee" error cannot even be surfaced.
- The log date is fixed to today; the UI offers no way to record a past day or to test the documented future/Friday/join-date guards.

## Source evidence

`resources/ts/pages/timesheet.vue` `logAttendance()`:

```ts
try { await AttendanceApi.create(...) ... }
catch (err) { error.value = translate('...failedToLogAttendance'); console.error(err) }
```

There is no `useToast` call in the function, while other HR pages consistently use toastification.

## Impact

The user cannot distinguish "saved" from "nothing happened", and the specific server reasons (`business.attendance_already_recorded`, `business.friday_weekend_no_attendance`, `business.future_attendance_not_allowed`, `business.attendance_before_join_date`) never reach them.

## Suggested direction

Adopt the toast + field-error pattern used by Payrolls: success toast with employee/date/status, `apiFieldErrors` mapped to the form, and expose the log date (bounded by today) so past corrections are possible.

## Evidence

- Live run: selecting an employee and pressing Present produced no toast and no visible alert containing an error (`TOASTS: ""`).
- Server validations read from `app/Http/Controllers/AttendanceController.php` `store()`.
