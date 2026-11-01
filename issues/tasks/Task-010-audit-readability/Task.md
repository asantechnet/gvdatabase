# Task Task-010 — Make audit rows and notifications readable

## Metadata
- Task ID: `Task-010`
- Title: `Make audit rows and notifications readable`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/11-audit-trail/issue.md` audit and notification slices; `issues/review/REPORT.md` §4 P2-8 Audits; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-11-AUDIT`, `ISSUE-11-NOTIFICATION`

## Objective
An auditor can read what changed, from what to what, by whom and when, without translating internal ids or asking the database.

## Context and problem
`audits.vue` renders `log.changes`, but `fieldLabel()` falls back to `pages.audits.otherInformation` when a column has no translation. A stage change therefore appears as "Applicants · Ahmad Wali — Other information: 5 → 6". The audit row does not show the field label, old/new stage names or a readable actor context. 550 seeded "System" rows marked "Needs audit" dilute the review queue. Notification copy is generic: locale key `...message` is "{resource} {record} was changed." with no stage, actor or action.

The case timeline actor and note are owned by Task-001; this task covers the audit page, audit API serialization and notification copy.

## Scope
### Included
- Serialize audit changes with a human field label and human old/new values, resolving foreign keys such as `current_stage_id` to stage names where the audit resource can do so.
- Never fall back to "Other information" for a known audited column; use the column name only as a last resort and include the raw value.
- Show actor name, IP address, request method and timestamp in the audit detail.
- Provide a filter or default scope that separates seeded/system entries from genuine pending changes, without deleting audit history.
- Make the audit table fit at 1280 px with record names wrapped to a bounded number of lines and the important columns visible.
- Rewrite stage-change notifications to name the record, the from/to stage and the actor.
- Keep audit review/undo permissions and existing heartbeat behavior unchanged.

### Excluded
- Changing what is audited or the audit schema.
- Reverting old audit rows or deleting seeded history.
- The case timeline UI (Task-001).
- Real-time heartbeat behavior and service-worker caching (do not touch).

## Preconditions and assumptions
- Audit rows store `old_values`, `new_values`, `changed_columns`, `subject_label`, `user_id`, `ip_address`, `method` and `created_at`; the resource can read them without schema changes.
- Assumption to validate: stage ids can be resolved to names with a bounded lookup for the affected resource. If not, cache a stage map for the page rather than issuing a query per row.
- Seeded rows are identifiable by `audited_by = 'System'`, `review_status`, or a seed marker; confirm the actual discriminator before filtering.
- The notification producer can access the applicant, old stage and new stage at the point it builds the message.

## Dependencies
- `Task-013` — audit table action column and accessible row controls use the shared table primitives.

## Contracts and boundaries
- Input/output: `GET /audit-logs` keeps its envelope and pagination; change entries gain `field_label` and `old_label`/`new_label` while keeping raw values for audit integrity.
- Compatibility guarantee: undo/revert still uses the raw stored values; display labels never replace the audit payload.
- Error behavior: an unresolvable foreign key returns the raw value plus a neutral label, not a 500.
- Permission/data scope: `view_audit_logs`, `view_team_audit_logs` and review permissions are unchanged; no audit row becomes visible to a user who could not see it before.
- Integration seam: one serializer is used by the list and the detail endpoint so labels cannot diverge.
- Files this task must not modify: `app/Models/AuditLog.php` (unless the discriminator requires a read-only helper), `resources/ts/pages/applicant-case.vue`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `app/Http/Controllers/AuditLogController.php` — list/detail serialization and system-row filter.
- `app/Http/Resources/AuditLogResource.php` — new human-readable change serializer.
- `resources/ts/pages/audits.vue` — columns, detail view and filter chips.
- `resources/ts/api/auditLog.ts` — typed change labels and filter parameters.

## Acceptance criteria
- [ ] Given a stage change from University Selection to University Application, when the audit row renders, then it shows the field label and both stage names, not numeric ids or "Other information".
- [ ] Given an audit entry for a known column, when it renders, then the field label comes from the audit/common translation and the raw column name is used only when no label exists.
- [ ] Given the audit detail, when it is opened, then actor, IP address, request method and timestamp are visible.
- [ ] Given the review queue contains seeded system rows, when the page opens, then genuine pending changes are distinguishable and can be viewed without the seeded rows, and no audit history is deleted.
- [ ] Given a stage change notification, when it is delivered, then it names the applicant, the from and to stages and the actor, and no generic "was changed" copy remains for this event.
- [ ] Given a 1280 px viewport, when the audit table renders, then the change and actor columns are visible and record names wrap to a bounded number of lines without pushing actions off-screen.
- [ ] Given undo/revert on an updated audit row, when it is submitted, then it still reverts the raw stored value exactly as before.

## Verification plan
- Automated: feature tests for change serialization (including stage id resolution), system-row filtering, notification copy and undo; run `php artisan test --filter=Audit` and `npm run typecheck`.
- Manual: make a stage change with a note, then inspect the audit row and the notification bell; filter system rows; open the detail and check actor/IP/method/time.
- Fixtures: one real stage change, a handful of seeded system rows and one updated financial row for undo. Non-production only.
- Regression: confirm heartbeat flags/cursors still work, audit review bulk actions still work, and no hidden row becomes visible to a team-scoped reviewer.
- Evidence: before/after audit row screenshots, notification text, test output, undo result.

## Implementation notes
Resolve labels in the resource, not in Vue, so exports and future consumers get the same reading. Keep raw values in the payload for revert and forensic use. For stage names, load a map once per request. For the notification, pass explicit old/new stage names from the workflow service rather than re-querying by id inside the locale string.

## Risks and rollback
- Risk: label resolution creates N+1 queries. Mitigation: preload a bounded map and assert query count.
- Risk: filtering system rows hides something a reviewer needs. Mitigation: make it a filter, not a destructive scope, and keep "All" available.
- Data migration: none. Rollback is code-only; raw audit data remains untouched.
- Stop condition: if seeded rows cannot be distinguished from genuine rows, stop and propose a marker rather than guessing.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
