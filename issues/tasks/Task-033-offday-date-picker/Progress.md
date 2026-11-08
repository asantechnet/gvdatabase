# Progress — Task-033

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (HR frontend owner)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Open My off days as the seeded employee, reproduce the detached calendar and fix the two picker configurations in resources/ts/components/paid-time-off/MyPaidTimeOffDialog.vue.`

## Acceptance criteria progress
- [ ] Given My off days is open, when the employee clicks From, then the calendar is anchored to the From input with the documented zero-offset transform and does not cover the dialog header or balance cards. — Evidence: `not yet available`
- [ ] Given the From calendar is open, when a weekday is clicked, then From shows that date, the calendar closes and the requested-day count updates. — Evidence: `not yet available`
- [ ] Given From is set, when the employee picks a later weekday in To, then To updates and the Request button state reflects the chosen range and reason. — Evidence: `not yet available`
- [ ] Given the dialog at desktop and 390 px in LTR/RTL and light/dark, when the pickers are used, then the calendar remains visible above the dialog and the form is usable. — Evidence: `not yet available`

## Work completed with evidence
- Browser review on 2026-11-07 reproduced the detached calendar and the non-applying day click. Screenshots are in `evidence/calendar-overlaps-dialog.png` and `evidence/calendar-detached-from-input.png`.
- No product files were changed by this review.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Manual Playwright CLI date selection in My off days | Local app, seeded employee | Failed as expected for this task | `evidence/calendar-detached-from-input.png` | From stayed 2026-11-07 after clicking November 9. |

## Decisions and deviations
- The review did not edit application code; the request was submitted on the default date to continue the allowance check.

## Blockers and open questions
- None. The broken configuration and the documented dialog convention are identified.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task record from the live two-user off-day review.
