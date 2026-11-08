# Progress — Task-043

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Localization owner with native Dari/Pashto review)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Collect the frozen key lists from Tasks 039-042 and add them to all six catalogs.`

## Acceptance criteria progress
- [ ] Given all Academy surfaces, when their keys are compared, then every key referenced by Tasks 039-042 exists under `academy.*` or `common.navigation.academy` in en, fa-AF and ps-AF, with non-empty values and matching placeholders. — Evidence: `not yet available`
- [ ] Given the three PHP catalogs, when the new business codes are translated, then `business.academy_term_undefined`, `business.academy_class_ended`, `business.academy_class_full`, `business.academy_enrollment_invalid`, `business.academy_certificate_not_eligible`, `business.academy_certificate_revoked` and `business.academy_certificate_duplicate` return specific sentences, never a generic default. — Evidence: `not yet available`
- [ ] Given the UI in Dari and Pashto, when the Academy nav, KPI cards, class/student tables, attendance grid and certificate dialogs render, then labels are translated, no raw key or English fallback appears, and RTL alignment is correct. — Evidence: `not yet available`
- [ ] Given the longest labels, when measured at 390 px, then navigation, table headers, dialogs and print controls do not truncate or overlap; the reviewer records the exact strings checked. — Evidence: `not yet available`
- [ ] Given the JSON files, when parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` and the key-parity check pass. — Evidence: `not yet available`
- [ ] Given the task diff, then it contains only the six catalogue files and no component, controller, schema, route or test change; existing org-scope keys are unchanged. — Evidence: `not yet available`

- [ ] Given the responsible-employee surfaces in Task-046, when the six catalogs are checked, then the seven frontend keys exist in all three locales with matching placeholders and `api.errors.business.applicant_stage_service_not_supported` returns a specific sentence in all three PHP catalogs. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Task-026's org-scope keys stay frozen; this task is the single writer of the six catalogs.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Added the Task-046 responsible-employee key freeze and stage-service refusal code.
