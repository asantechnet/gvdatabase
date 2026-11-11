# Progress — Task-001

## Status snapshot
- Status: `Done`
- Owner: `Product owner with engineering lead`
- Last updated: `2026-11-10`
- Current focus: `Approved employee-only policy recorded; implementation handed to Task-002 and Task-003`
- Blocker: `None`
- Next action: `Task-002 applies the employee-only route mount, manager grant and regression tests.`

## Acceptance criteria progress
- [x] Given the three edit-window findings, when the decision is recorded, then it names the governed resource set and the reason for that choice. — Evidence: `decision.md` selects employee-only, names the employee update endpoint, and explains the task/contract exemptions.
- [x] Given the chosen policy, when an editor reaches the window boundary, then the decision names the exact exempt workflows, the override permission, and whether contracts are governed. — Evidence: `decision.md` lists every exempt module, retains `override_edit_window`, and explicitly exempts contracts.
- [x] Given the approved decision, when downstream tasks read it, then it states the required server error, UI state, rollout notes, and product-owner sign-off. — Evidence: `decision.md` records `422 business.edit_window_expired`, employee resource/UI state, no backfill, route-mount rollout, and product-owner approval.

## Work completed with evidence
- Product owner selected **employee-only** enforcement.
- Recorded the governed employee update endpoint and excluded documents, leave requests, tasks, contracts, applicants, visitors, attendance, finance, and master data.
- Kept the 60-minute duration, `422 business.edit_window_expired` contract, and `edit_window_overridden` audit requirement.
- Defined the role split: `manager` gets `edit_employees` without override; `admin` retains both; `staff` self-service is unchanged.
- Defined employee-resource UI state and disabled-edit behavior for Task-003.
- Confirmed Task-004 must not add contract edit-window enforcement; it only fixes extension integrity.
- No product source, tests, migrations, routes, configuration, or translations were changed by this decision task.

## Files changed and why
- `Tasks/Task-001-decide-edit-window-policy/decision.md` — approved employee-only policy and downstream contract.
- `Tasks/Task-001-decide-edit-window-policy/Progress.md` — decision evidence and status.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | Manual comparison against `issuesv2/README.md` and EW-001–EW-005 | Local repository | PASS | `decision.md` | Employee-only resolves the global task freeze and makes the contract bypass intentional. |
| 2026-11-10 | Product-owner approval | Current session | PASS | `decision.md` | Product owner selected employee-only enforcement. |
| 2026-11-10 | No product-file change check | Local repository | PASS | Task file list | Only decision/progress records were written. |
| 2026-11-10 | Task validator | Local Python | PASS | Terminal output | Task set remains structurally valid after the decision status change. |

## Decisions and deviations
- Employee-only enforcement was chosen over application-wide enforcement or an explicit multi-resource list.
- Contracts are intentionally exempt from the edit window; their extension integrity remains a separate Task-004 concern.
- Tasks and all other workflow modules are exempt.
- `manager` is the seeded operational employee editor; `admin` keeps the override. Staff self-service remains unchanged.
- No product behavior was changed by this decision task.

## Blockers and open questions
- None.

## Handoff notes
- Task-002 is unblocked and must mount the middleware only on the employee update route, adjust the manager grant, and preserve the existing error/audit contracts.
- Task-003 is unblocked after Task-002 and owns the employee resource/UI state.
- Task-004 remains limited to extension integrity and must not enforce the employee window on contracts.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Product owner approved employee-only enforcement; recorded `decision.md` and handed implementation to Task-002/Task-003.
