# Progress — Task-034

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (HR workflow owner)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Reproduce the Government ID validation on Edit Staff, trace the staff-form, frontend schema and backend update rules, then record the decision and follow-up owner.`

## Acceptance criteria progress
- [ ] Given an existing staff record with no government ID, when the operator opens Edit Staff and changes only the allowance, then the exact blocking validation layer or layers are documented with file and line evidence. — Evidence: `not yet available`
- [ ] Given those constraints, when the decision is recorded, then it names the chosen option, the rejected alternatives, the data and API impact, and the owner. — Evidence: `not yet available`
- [ ] Given the decision, when the task closes, then a follow-up implementation task exists with file ownership, or the rejection is recorded with a concrete reason. — Evidence: `not yet available`

## Work completed with evidence
- Browser review on 2026-11-07 observed `Government ID is required` when saving a 20-day allowance for Ahmad Karimi, whose seeded record has no `id_number`. The allowance persisted only after entering a synthetic ID.
- No product files were changed by this review.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Manual Playwright CLI Edit Staff save | Local app, seeded employee | Failed as expected for this task | None | Inline error appeared under Government ID. |

## Decisions and deviations
- The review did not decide the product rule; this task records the gap and requires an explicit owner decision.

## Blockers and open questions
- Decision required: seed demo identity data, require the ID only on create, or allow a partial allowance update. The product owner must choose.

## Handoff notes
- Not ready for handoff; the decision has not been made.

## Activity log (append-only)
- `2026-11-07` Created the task record from the live two-user off-day review.
