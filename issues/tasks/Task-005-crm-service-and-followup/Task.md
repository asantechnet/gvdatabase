# Task Task-005 — Give CRM visitors a controlled service and a real follow-up queue

## Metadata
- Task ID: `Task-005`
- Title: `Give CRM visitors a controlled service and a real follow-up queue`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/05-followup-filter/issue.md`; `issues/review/issues/15-crm-service-taxonomy/issue.md`; `issues/review/REPORT.md` §4 P2-3, P2-9; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-05`, `ISSUE-15`

## Objective
Visitor service is a controlled vocabulary shared with cases and reports, and the Follow-up list is a truthful flagged queue with a visible identity and empty state.

## Context and problem
`VisitorController` validates `service` as `required|string|max:255` and the Add Visitor dialog is a free textbox. Any typo permanently fragments service reporting. `grade_score` and age lack explanatory labels or ranges in the form.

The Follow-up filter itself was repaired before this task set: `visitor.vue` now sends `follow_up=1` for `?list=follow-up`, the page title/icon/row actions change, and `VisitorController::index()` filters when the parameter is present. The demo database has 6 visitors and 0 flagged, so the correct follow-up result is empty. This task locks that behaviour with a regression test and completes the remaining CRM presentation gaps.

## Scope
### Included
- Replace the visitor service text field with a select of the four built-in services, using the same vocabulary as cases and reports.
- Validate the service server-side with the built-in service rule on create and update.
- Normalize existing visitor service values to canonical keys, preserving unknown values in a reviewable way rather than silently deleting them.
- Return a canonical service key and a translated label from the visitor resource; update list, print report and filters to use the label.
- Label the qualification fields with defined meaning and range: percentage as a 0-100 score and age with min/max.
- Lock the follow-up queue behaviour with tests: only flagged rows, distinct title, empty state, star/unstar moves a row between lists, and the nav item is active.
- Name every visitor row action for assistive technology.
- Ensure the Add/Edit dialog notes field is not covered by the sticky footer at 720 px.

### Excluded
- Changing what `follow_up` means or adding a next-action/date field (a future CRM enhancement).
- Visitor-to-applicant conversion rules.
- Shared table sticky columns and pagination (Task-013).
- Contract creation from a follow-up row.

## Preconditions and assumptions
- `App\Support\BuiltInService::ALL` is the canonical four-service list (`scholarship`, `private`, `test_offer`, `academy`).
- Existing visitor rows may contain display labels such as "Scholarship"; the migration maps both canonical keys and known labels case-insensitively.
- Assumption to validate: no external integration writes visitor service values. If one does, the integration must map to the canonical key in this task.
- The follow-up list is a filtered view of the same visitor table, not a separate table.

## Dependencies
- `Task-013` — shared table mobile cards and accessible row-action primitives must exist before the responsive and naming acceptance is tested.

## Contracts and boundaries
- Input/output: `GET/POST/PUT /visitors` accept canonical service keys; `VisitorResource.service` returns the canonical key and adds `service_label`.
- Compatibility guarantee: existing filters and reports that group by service receive canonical values after normalization; unknown historical values are retained and surfaced, not dropped.
- Error behavior: an unsupported service returns HTTP 422 with `errors.service`; the UI shows a field-level message.
- Permission/data scope: visitor create/edit permissions and consultant locking remain unchanged; the follow-up toggle keeps its existing permission.
- Integration seam: the service list must come from one shared frontend constant or API option, not a duplicated array.
- Files this task must not modify: `resources/ts/components/responsive-data-table/**`, `app/Models/Applicant.php`.

## Exclusive file ownership
- `app/Http/Controllers/VisitorController.php` — service validation and follow-up filter.
- `app/Http/Resources/VisitorResource.php` — canonical key plus label.
- `app/Models/Visitor.php` — service constants or accessor if needed.
- `resources/ts/pages/visitor.vue` — select, labels, queue identity, action names and dialog layout.
- `resources/ts/api/visitor.ts` — service key and label types.
- `database/migrations/2026_11_01_000003_normalize_visitor_services.php` — new data normalization migration.

## Acceptance criteria
- [ ] Given the Add Visitor dialog, when the service field is focused, then it offers exactly the four built-in services and does not accept arbitrary text.
- [ ] Given a direct API request with an unsupported service, when it is submitted, then it is rejected with a field-level `service` error and no visitor row is written.
- [ ] Given historical visitor rows with label-case service values, when the migration runs, then they become canonical keys and the resource returns both key and translated label; unknown values remain visible for review.
- [ ] Given zero flagged visitors, when the Follow-up list opens, then it shows an empty state, a follow-up-specific title and an active navigation state, and the Visitors list shows the unflagged visitors.
- [ ] Given a visitor is starred, when the lists reload, then it leaves the Visitors list and appears in Follow-up; unstarring reverses that exactly.
- [ ] Given the qualification fields, when the form renders, then percentage is labelled as a 0-100 score and age shows its allowed min/max, and both are enforced server-side.
- [ ] Given a visitor row at 1280 px, when a keyboard or screen reader user reaches the actions, then every icon button has an accessible name.
- [ ] Given a 720 px tall Add Visitor dialog, when the form is scrolled to the notes field, then the sticky footer does not cover the field or its error text.

## Verification plan
- Automated: feature tests for service rejection, normalization and follow-up filtering; run the focused visitor tests and `npm run typecheck`.
- Manual: create a visitor, star it, open Follow-up, unstar it, confirm both lists and the empty state; check the add dialog at 1280x720 and 390x844 in LTR and RTL.
- Fixtures: one visitor with a canonical service, one with a legacy label, one flagged and one unflagged; clean up after the run.
- Regression: confirm visitor print, conversion to applicant and consultant locking still work; confirm no visitor disappears after normalization.
- Evidence: test output, before/after service values, follow-up and empty-state screenshots, accessible-name inspection.

## Implementation notes
Keep the canonical service keys lower_snake_case to match `BuiltInService`. Do not silently coerce unknown values to scholarship; that would falsify history. For the migration, use a mapping table and leave unmapped rows unchanged, logging them in the migration output. The follow-up filter is already correct; treat the regression test as a lock, not a rewrite.

## Risks and rollback
- Risk: normalization could merge distinct legacy values incorrectly. Mitigation: map exact known labels only, keep unknown values, and make the migration reversible with a backup column or a down migration that cannot lose the original text.
- Risk: making service a select breaks imports. Mitigation: search for other writers and provide a canonical mapping.
- Data migration: one forward migration plus a reversible mapping. Rollback restores the original strings from a temporary audit copy.
- Stop condition: if the four-service vocabulary is not final for visitors, stop and get the product decision before migrating.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
