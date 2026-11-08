# Progress — Task-035

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (full-stack implementer with audit and RBAC review)`
- Last updated: `2026-11-07`
- Current focus: `Not started; the row-trail contract, central resource map and shared table rail are defined in Task.md.`
- Blocker: `None`
- Next action: `Implement the permission-scoped latest-per-record audit read and its feature tests, then build the shared table rail on top of it.`

## Acceptance criteria progress
- [ ] Given a user with `view_audit_logs` opens any covered register with audited rows, then each row carries exactly one rail state derived from its latest audit entry: Created is green, Updated is amber, Deleted is red when the register already returns a deleted row, and Restored is amber with the existing restored label; rows without history or without permission are neutral.
- [ ] Given an Updated row, then only the cells whose visible column maps to a changed audit field receive the change emphasis, each with an accessible label/tooltip naming the field and its before → after values; changed fields that have no visible column are listed in the row's audit summary, and unchanged cells are not marked.
- [ ] Given a Created row, then the row is identifiable as created but no fabricated field diff is shown; given a Deleted row that the current table scope returns, then the row is identifiable as deleted, read-only and its audit action opens the deletion entry.
- [ ] Given a row with at least one audit entry and the read permission, then a labelled row action opens `/audits?audit={latestAuditId}` and the exact audit detail is shown, even when that audit is not on the Audits page's current list page; without an audit id the action is absent or disabled with an explanation rather than navigating to a blank search.
- [ ] Given a user without `view_audit_logs` or a caller whose audit read returns 403, then no legend, rail, changed-cell emphasis or audit action is rendered, no error toast appears, and the table's existing pagination, sorting, filters, selection, print and mobile cards are unchanged.
- [ ] Given a register page with 25-100 rows, then the table issues at most one row-trail enrichment request per loaded page/filter state (never one per row), sends no more than 100 distinct ids, supersedes stale requests, and the server returns only the caller-visible latest entry per id.
- [ ] Given the coverage matrix, then every `AuditRegistry` key plus `account_deactivations` and `audit_settings` is represented: shared-table registers are automatic, the five raw-table pages are explicitly integrated, parent/detail-only keys point to their parent record action, and `audit_settings` is identified as the audit-configuration surface rather than silently dropped.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark themes, then the rail/legend/action remain readable without horizontal overflow, state is not conveyed by colour alone, changed-cell tooltips are keyboard reachable, focus order is preserved and reduced-motion preferences are respected.
- [ ] Given auditing is disabled for a resource, or the resource has only pre-existing history, then the table renders neutrally without an error; when history exists and the user has permission, the action can still open it; no list scope or deleted-row visibility is changed.
- [ ] Given the completed implementation, then `php artisan test --filter=AuditTrailTest`, the existing audit management/readability tests, `npm run typecheck`, the icon build warning check and the manual register checks in the verification plan pass, and no duplicate paginator, toast, second audit viewer or per-row fetch is introduced.

## Work completed with evidence
- Task contract created; implementation has not started. No product file was changed by this task record.

## Files changed and why
- None yet. The existing files named in Task.md are forward-ownership transfers, not changes made by this task record.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Taskifier structural validation | Local | Not run yet | — | Run `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` after the registry update. |

## Decisions and deviations
- One full-stack task is used because the table rail, resource map, endpoint and row action share one contract; splitting them would leave an unusable intermediate state.
- The shared table is the primary integration point. Five raw-table pages are included explicitly; other auditable resources without a row table are mapped to their parent record action.
- Deleted-state styling is supported only where a register already returns a deleted row. This task does not add trash scopes to live registers; that remains a separate product decision.
- The existing Audits page deep link is reused; no second audit viewer is created.
- No locale files are owned: the implementation must reuse existing `pages.audits.*` and `common.*` keys.

## Blockers and open questions
- None. The per-endpoint resource map is the only implementation detail that may need an explicit override if a page has two auditable tables under one URL; the fallback is no rail, which must be recorded if encountered.

## Handoff notes
- Ready for implementation. Preserve the five forward ownership transfers in the old task records: `routes/api.php` from Task-009, `resources/ts/api/axios.ts` from Task-018, `resources/ts/components/responsive-data-table` from Task-013, `resources/ts/pages/service-contracts.vue` from Task-002, and `resources/ts/pages/contract_list.vue` from Task-003.
- The existing audit page, heartbeat, review/undo and list scoping are regression surfaces, not change surfaces.

## Activity log (append-only)
- `2026-11-07` Created Task-035 from the user's audit-rail request and current repository inspection; status `Ready`.
- `2026-11-07` Forward ownership of the API route file moved to Task-038 for the Academy route tree; the row-trail read remains part of this task's delivered behaviour.
- `2026-11-07` Forward ownership of the paths needed for Task-046 moved to Task-046; the delivered behaviour remains historical evidence.
