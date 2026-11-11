# Progress — Task-008

## Status snapshot
- Status: `Done`
- Owner: `Product owner with finance and Academy leads`
- Last updated: `2026-11-10`
- Current focus: `Approved decision recorded; handed to Task-009`
- Blocker: `None`
- Next action: `Task-009 implements the approved retirement of Academy counselor commission.`

## Acceptance criteria progress
- [x] Given the Academy enrollment evidence, when the decision is recorded, then it chooses one path: create or link a contract line, use another durable commission source, or retire Academy counselor commission. — Evidence: `decision.md` records the approved retirement path and the reason.
- [x] Given the chosen path, when the decision is recorded, then it defines fee, currency, seller or owner, cancellation and refund treatment, and existing-enrollment handling. — Evidence: `decision.md` records the teacher-only owner, class-fee/base-currency basis, lifecycle treatment, no backfill, and void/preserve rules.
- [x] Given the decision, when Task-009 reads it, then the scope is explicit enough to implement without inventing a commission policy. — Evidence: `decision.md` names the required Task-009 behavior, UI boundary, and regression expectations.

## Work completed with evidence
- Recorded the product-owner approval to retire Academy counselor commission.
- Defined teacher class commission as the only Academy commission path, with the class teacher as owner and the class fee/base currency as the basis.
- Defined no backfill for existing direct enrollments, no counselor accrual from Academy contract lines, and no clawback of settled money.
- Explicitly excluded staff-list UI changes.
- No product source, tests, migrations, routes, configuration, or translations were changed by this decision task.

## Files changed and why
- `Tasks/Task-008-decide-academy-commission-path/decision.md` — the approved Academy commission policy and handoff contract for Task-009.
- `Tasks/Task-008-decide-academy-commission-path/Progress.md` — decision evidence and status.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | Manual comparison against `commission-issues/LOGIC-20261110-004/issue.md` | Local repository | PASS | `decision.md` | Decision matches the confirmed unreachable-counselor path and keeps teacher commission separate. |
| 2026-11-10 | Product-owner approval | Current session | PASS | `decision.md` | Approved: only teacher role/position receives Academy commission; no staff-list UI change. |
| 2026-11-10 | No product-file change check | Local repository | PASS | Task file list | Only the decision and progress records were written. |

## Decisions and deviations
- Retire Academy counselor commission rather than create or link a contract line.
- Keep teacher class commission unchanged and separate.
- Do not backfill or delete historical money; unsettled retired-path accruals are voided only by a future commission sync, while settled accruals remain.
- Do not change the staff list UI.

## Blockers and open questions
- None.

## Handoff notes
- Task-009 is the implementation consumer of `decision.md`.
- Acceptance requires proof that direct enrollment and Academy contract lines produce no counselor accrual, while teacher class completion still produces the existing teacher accrual.
- The product owner explicitly does not want a staff-list commission presentation change.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Product owner approved retirement of Academy counselor commission; recorded `decision.md` and handed implementation to Task-009.
