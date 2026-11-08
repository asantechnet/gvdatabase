# Progress — Task-032

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (HR frontend owner)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Reproduce the normal approval toast on /leave-requests, then fix the grant handler and add the per-employee balance display in resources/ts/pages/leave_requests.vue.`

## Acceptance criteria progress
- [ ] Given a pending full-day request inside the employee's allowance, when the approver clicks Approve Selected, then the PATCH sends `force_off_day` as boolean false and the row becomes Off day with the approver recorded. — Evidence: `not yet available`
- [ ] Given that approval, when the ledger reloads, then the employee's allowance, used and remaining days are visible on the Off Day Requests page and show the expected drop (for example 20 → used 1 → remaining 19). — Evidence: `not yet available`
- [ ] Given a selection that exceeds the allowance, when the approver uses Approve as paid exception, then the existing justification dialog still appears, requires a note and records the off-day override. — Evidence: `not yet available`
- [ ] Given a viewer without `grant_leave_requests`, when they open the ledger, then no approval action is offered and the read-only balance display still works. — Evidence: `not yet available`

## Work completed with evidence
- Browser review on 2026-11-07 reproduced the blocked approval. The toast `Force Off Day: Choose a valid option.` is captured in `evidence/approval-validation-error.png`.
- The approved row and the ledger without any balance column are captured in `evidence/ledger-after-approval.png`.
- The employee balance after the service-level approval (allowance 20, used 1, remaining 19) is captured in `evidence/employee-balance.png`.
- No product files were changed by this review.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Manual Playwright CLI approval on /leave-requests | Local MySQL, seeded super admin | Failed as expected for this task | `evidence/approval-validation-error.png` | Client validation blocked the PATCH before it was sent. |

## Decisions and deviations
- The review did not edit application code. The pending row was approved through the same grant service only to verify the downstream allowance math; that bypass is not the intended fix.

## Blockers and open questions
- None. The defect and its root cause are known.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task record from the live two-user off-day review.
