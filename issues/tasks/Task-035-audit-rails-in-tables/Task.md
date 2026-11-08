# Task Task-035 — Show record audit trails in every auditable table

## Metadata
- Task ID: `Task-035`
- Title: `Show record audit trails in every auditable table`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with audit and RBAC review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-07: audit rails must be visible in every auditable table, colour-coded by Created/Updated/Deleted, with changed cells identified and an RBAC-backed action that opens the exact audit row; repository inspection 2026-11-07`
- Requirement / behavior IDs: `AUDIT-RAILS-001`, `AUDIT-RAILS-002`, `AUDIT-RAILS-003`, `AUDIT-RAILS-004`

## Objective
A user who is allowed to read audits sees the latest audit state of every record directly in the register they are already using: created rows read green, updated rows read amber with the exact changed cells identified, deleted-state rows read red where the register already exposes them, and one labelled row action opens that record's latest audit entry. Nothing changes for users without audit permission, and no table gets a second paginator or an N+1 audit lookup.

## Context and problem
`AuditRegistry` defines the auditable resource keys and `AuditPresenter` already serializes events and field-level changes for the Audits page. The Audits page can deep-link an audit row with `?audit={id}` and opens the detail even when the row is not on the current list page. However, the business registers show no audit state at all: `IncomeController` writes audit rows and `income.vue` renders the receipt ledger with no hint that a receipt was later updated; payroll, staff, leave, contract and catalogue registers behave the same way.

The shared table (`resources/ts/components/responsive-data-table`) is used by nearly every register, including payroll, staff, leave, assets, applicants, contracts, tasks/settings, account deactivation and the paid-time-off dialogs. Five pages still render raw `VTable`/`VDataTable` registers: `contract_list.vue`, `service-contracts.vue`, `cancelled_contracts.vue`, `educational-master-data.vue` and `academy-data.vue`. A page-by-page patch would duplicate the same audit fetch, colour, tooltip and action in dozens of files. The feature therefore needs one record-level read contract, one central list-to-resource map, and one shared table/row-rail implementation, with the five raw-table pages integrated explicitly.

This task supersedes the forward read ownership of the shared table directory, the Axios client and the two older contract-register pages. Forward ownership of the API route file moved to Task-038 on 2026-11-07 for the Academy route tree; the row-trail read remains part of this task's delivered behaviour. The previous implementations remain historical evidence; this task adds the audit rail without changing their domain behaviour.

## Scope
### Included
- Add a permission-gated backend read that accepts one auditable `resource` key and up to 100 record ids and returns the latest audit entry per record, including the exact audit id, event, changed fields with labels and before/after values, actor, timestamp, audit state and resolution. Team-scoped reviewers remain scoped by the existing audit scope rules.
- Register the read beside the existing audit routes and before the `audit-logs/{auditLog}` detail route so the literal path is not swallowed.
- Add a central frontend map from list endpoints to `AuditRegistry` resource keys, covering every auditable resource that has a table, and tag list rows without mutating the API payload.
- Extend the shared table so a tagged register automatically gets: a compact Created/Updated/Deleted legend, a row rail, changed-cell emphasis for updated rows, an accessible changed-field summary, and one row action that navigates to `/audits?audit={latestAuditId}`.
- Reuse the existing `pages.audits.events.*`, `pages.audits.fields.*`, `pages.audits.title` and `common.actions.*` copy; do not fork new translation subtrees or hand-edit generated icon CSS.
- Gate the rail, legend and action on the same audit-read predicate the Audits page/navbar uses (`view_audit_logs`, including the existing super-admin behaviour). The new API independently enforces the permission and team scope.
- Add the endpoint to the frontend API-authorization map and keep the audit read quiet: a missing permission, disabled scope or transient failure must not produce a toast or block the table.
- Integrate the five raw-table pages explicitly, either by adopting the shared table or by consuming the same audit-trail composable and row primitives. Preserve their existing columns, filters, print and actions.
- Cover every key in `AuditRegistry::resources()` plus the two additional audit settings keys (`account_deactivations`, `audit_settings`) in the coverage matrix. Keys without a row table are explicitly assigned to their parent record action or excluded as the audit-configuration surface; no auditable key is silently omitted.
- Handle the audit `restored` event as an updated/amber state with the existing `pages.audits.events.restored` label. Unknown custom events remain neutral.
- Support 390, 768 and 1280 px, LTR and RTL, light and dark themes, keyboard focus, non-colour cues and reduced motion.
- Preserve existing table pagination, sorting, filtering, selection, mobile cards, actions, print, empty state and loading skeleton.

### Excluded
- Changing what is audited, the audit schema, audit retention, review/undo semantics, or the audit heartbeat.
- Adding deleted rows to live registers. `Deleted` styling applies only where a register already returns an archived/deleted row; adding trash or archived-row list scopes to every controller is a separate product decision and task.
- Building a second audit page, a separate audit drawer, or a new notification channel.
- Changing `review_audit_logs`, `revert_audit_changes`, `manage_audit_scope`, `view_all_audits` or team-scope rules.
- Editing the Audits page's existing filters, bulk review, detail dialog or `?audit=` deep-link behaviour beyond regression verification.
- Adding per-row audit fetches, websockets, background sync or a mutation queue.
- Changing income receipt printing, contract printing, payroll calculations or list scoping.

## Preconditions and assumptions
- `AuditLog` stores `subject_type`, `auditable_id`, `event`, `old_values`, `new_values`, `changed_columns`, `user_id`, `employee_id`, `audited_at` and `review_status`; `AuditPresenter` already returns readable changes and hides sensitive fields.
- Assumption to validate: every auditable table can be identified by its list request URL without ambiguity. If one page has several auditable tables under the same URL, the central map must be extended with an explicit table-scoped override; falling back to "no rail" is safer than guessing.
- Assumption to validate: the latest audit for a record is the correct rail state. A later review decision does not change the row's latest created/updated/deleted event; review status remains a separate audit-state signal in the detail and tooltip.
- Assumption to validate: `?audit={id}` remains the stable deep link and the Audits page can open a detail for a row that is not on its current page. If either is false, fix the deep link inside this task's owned `auditLog`/page boundary or report a blocking contract gap rather than adding a second audit viewer.
- Assumption to validate: `view_audit_logs` is the UI gate used by the existing Audits navigation. `view_team_audit_logs` alone does not make the Audits route reachable, so it must not expose a row action that leads to a forbidden page.
- Assumption to validate: a page of 25-100 rows can be enriched with one bounded request. If the supported tables can exceed 100 visible rows, paginate the enrichment request rather than issuing one request per row.
- The seeded demo database has auditing opt-in per resource. Verification enables only the resources under test and restores their prior settings.
- No migration is expected. `audit_logs` already has the indexes needed for `subject_type` + `auditable_id` lookup; add an index only if the query plan proves it necessary.

### Coverage matrix
Every key in `AuditRegistry::resources()` appears exactly once below, plus the two keys the audit settings screen adds. “Shared table” means the page already renders through `resources/ts/components/responsive-data-table`, so the rail is automatic once the list endpoint is mapped; no page file is edited for that key.

- **Shared table (28):** `applicants`, `applicant_stages`, `attendances`, `boards`, `contract_policies`, `contract_templates`, `contract_template_versions`, `contract_types`, `currencies`, `daily_reports`, `educational_bonus_sources`, `employees`, `employee_advances`, `expenses`, `incomes`, `leaves`, `off_days`, `permissions`, `positions`, `salaries`, `salary_payments`, `tags`, `tasks`, `users`, `announcements`, `assets`, `visitors`, `worthy_employees`.
- **Explicit raw-table pages (16):** `educational_scholarships`, `academy_classes`, `subjects`, `tests`, `skills`, `programs`, `times`, `durations`, `teachers` (educational master data and academy data); `contracts`, `applicant_contracts` (contract list, service contracts and cancelled contracts); `countries`, `education_levels`, `languages`, `semesters`, `universities` (educational master data).
- **Parent/detail record action (13):** `private_applicant_details`, `scholarship_applicant_details`, `test_applicant_details`, `academy_applicant_details`, `applicant_attachments`, `applicant_universities`, `case_wins`, `comments`, `offices`, `settings`, `attachments`, `subtasks`, `task_attachments`. These render inside a case, task, attachment dialog or settings form rather than a standalone register; the parent row's audit action is the entry point and no orphan table is invented.
- **Additional audit settings keys (2):** `account_deactivations` is a shared-table register in Account settings; `audit_settings` is the audit-configuration table itself and is deliberately excluded from its own rail to avoid an audit rail on the audit settings control. Its existing `approve/reject` audit rows remain available on the Audits page.
- **Raw-table files owned by this task:** `resources/ts/pages/contract_list.vue`, `resources/ts/pages/service-contracts.vue`, `resources/ts/pages/cancelled_contracts.vue`, `resources/ts/pages/educational-master-data.vue`, `resources/ts/pages/academy-data.vue`.

## Dependencies
- `Task-010` — delivered the readable audit presenter, field labels, actor context and `?audit=` detail behaviour this feature consumes.
- `Task-013` — delivered the shared table, mobile cards and accessible action primitives that this feature extends.
- `Task-018` — delivered the one-toast API error contract and quiet/background request behaviour that the audit enrichment must preserve.

## Contracts and boundaries
- Input/output: new `GET /api/audit-logs/row-trails` accepts `resource` (one `AuditRegistry` key or one of the two additional audit-settings keys) and `ids[]` (1-100 distinct positive integers). It returns `{ success: true, data: [{ id, audit_id, event, changed_fields, created_at, changed_by, audit_state, resolution }] }`; `changed_fields` uses the existing `field`, `field_label`, `before`, `after`, `old_label`, `new_label` shape. The response is a latest-per-id set, not a paginated history.
- Permission/data scope: `view_audit_logs` is required. Existing `scopeToActor`/team-scope and super-admin rules apply unchanged; a team-scoped reviewer must not receive a record outside their visible employees. A caller without permission gets 403 and the table renders without rails.
- Error behaviour: unknown resource, empty ids, more than 100 ids, duplicate ids or non-integer ids return the repository's normal 422 validation shape; the frontend treats any enrichment failure as "no audit rail" and shows no toast, no broken table and no retry loop.
- Navigation: the row action uses the latest audit id and the named `audits` route with query `{ audit: String(id) }`. A missing/late deleted record is not invented; if no audit entry exists, the action is absent or disabled with an explanatory accessible label.
- Compatibility guarantee: rows, API payloads, table columns, pagination, selection, filters, sorting, print and mobile cards are unchanged when the user lacks permission, the resource has no map, auditing is disabled and no history exists, or the enrichment request fails. Do not add a visible audit column that is empty for every row.
- Integration seam: one list-to-resource map and one row-context mechanism are shared by every table. Pages do not call the audit endpoint directly, do not clone the colour/tooltip logic and do not add page-level audit toasts. The five raw-table pages use the same composable/primitives, not a second implementation.
- Performance: one bounded enrichment request per loaded page, cancelled or superseded when the page/filter changes; no per-row audit queries; no more than 100 ids per request; bounded relation eager loads and no unbounded `AuditPresenter` label fan-out.
- Files this task must not modify: `app/Http/Controllers/AuditLogController.php` (read-only dependency; the new read lives in its own controller), `resources/ts/pages/audits.vue` (existing deep link is the navigation target), `resources/ts/api/auditLog.ts` (existing audit read types remain stable), `app/Models/AuditLog.php`, `app/Support/AuditRegistry.php` (read-only registry), all files under `resources/ts/plugins/i18n/locales`, all generated icon/PWA output, and the domain controllers/resources of the registers whose list behaviour must not change.

## Exclusive file ownership
- `app/Http/Controllers/AuditTrailController.php` — new record-level latest-audit read, permission and team scope.
- `app/Support/AuditPresenter.php` — reuse and, only if required, extend the single audit serializer for the new read without changing existing consumers.
- `resources/ts/api/auditTrail.ts` — new typed row-trail request and response contract.
- `resources/ts/api/axios.ts` — central list-response tagging for the audit resource map; preserve the existing error/quiet contract. Forward ownership from Task-018.
- `resources/ts/components/responsive-data-table` — shared table rail, legend, changed-cell emphasis, mobile summary and row action. Forward ownership from Task-013.
- `resources/ts/components/audit/AuditTrailLegend.vue` — compact, accessible Created/Updated/Deleted legend.
- `resources/ts/components/audit/AuditTrailButton.vue` — permission-gated row action with accessible name and audit deep link.
- `resources/ts/composables/useAuditTrail.ts` — batch enrichment, latest-state selection, deduplication, cancellation and quiet failure handling.
- `resources/ts/utils/auditResources.ts` — one explicit list-endpoint-to-`AuditRegistry` resource map, including the two audit-settings keys.
- `resources/ts/utils/auditTrail.ts` — event-to-state mapping, field/header mapping and changed-value formatting helpers.
- `resources/ts/utils/apiAuthorization.ts` — add the row-trails endpoint to the existing client permission map.
- `resources/ts/pages/contract_list.vue` — integrate the shared audit rail into its raw contract register. Forward ownership from Task-003.
- `resources/ts/pages/cancelled_contracts.vue` — integrate the shared audit rail into its raw contract register.
- `resources/ts/pages/educational-master-data.vue` — integrate the shared audit rail into its catalogue table.
- `resources/ts/pages/academy-data.vue` — integrate the shared audit rail into its academy catalogue table.
- `tests/Feature/AuditTrailTest.php` — new feature coverage for the read contract, permissions, team scope, latest-per-record, limits, disabled audit and query budget.

Forward ownership of the contract-generation, applicant, stage, commission and contract-register paths needed by Task-046 moved on 2026-11-07; this task's delivered behaviour remains historical evidence.

## Acceptance criteria
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

## Verification plan
- Automated: `php artisan test --filter=AuditTrailTest`; run `php artisan test --filter=AuditManagementTest` and `php artisan test --filter=AuditReadabilityTest` as regressions; run `npm run typecheck`; run the icon generator check (`npm run build:icons`) and treat a missing `tabler-history`/chosen rail icon warning as a failure. If a component-test harness exists, add focused tests for the map, latest-state selection and action gating; otherwise record the source checks and manual evidence.
- Automated query budget: create one page of 25 records with several audit rows each and assert the row-trail endpoint returns one entry per id with a bounded query count; assert 101 ids, an unknown resource and a duplicate/id type violation return validation errors.
- Automated permission/scope: a user without `view_audit_logs` receives 403; a team-scoped reviewer receives only visible employees' entries; a super admin/view-all reviewer receives the requested set; the response never contains a hidden field.
- Manual: enable auditing for incomes, expenses, employees, leaves, off-days, contracts and one catalogue. Create, update and delete a record of each shape; open its register and verify green/amber/red states, changed cells, tooltip values and the exact audit detail. Repeat at 390, 768 and 1280 px, LTR/RTL, light/dark, with keyboard-only traversal. Verify mobile cards and a table with no audit history.
- Manual raw-table pages: contract register (active, cancelled, service contracts), educational master data and academy data; verify the rail and action appear without losing existing columns, filters, print or actions.
- Fixtures: non-production records only; enable audit scope for the resources under test, create at least one created-only row, one updated row with two changed visible fields and one changed hidden field, one deleted/archived row for a surface that already returns it, and one row with no history. Restore audit settings and delete fixtures after verification.
- Regression: existing audit list/review/undo, audit heartbeat, notification deep link, shared table pagination/mobile cards, income receipt print, contract print, payroll and leave actions. Confirm no new `api-error` toast or duplicate request.
- Evidence: endpoint response samples, query-count output, permission/scope test output, screenshots of the legend/rails/changed cells/action at four viewport/direction combinations, the opened exact audit, and typecheck/icon-test output.

## Implementation notes
Build the backend read as a dedicated controller action rather than overloading the review list: validate the resource against `AuditRegistry`, resolve the latest audit id per `auditable_id` with one grouped subquery, match legacy rows whose `subject_type` is null through the registry model's `auditable_type`, eager-load the actor relations, and serialize each row with the existing presenter. Apply the same actor scope used by `AuditLogController`; do not expose a record the caller could not see in the Audits page. Register the literal route before `audit-logs/{auditLog}`.

On the client, keep one explicit URL-to-resource map and one table-level enrichment path. The cleanest seam is a central Axios response tag that the shared table can read after Vue's reactive proxy wrapping (a non-enumerable symbol property on each list item, or an equivalent proxy-safe context map); do not add enumerable audit fields to API payloads, mutate typed objects or make every page pass the resource key manually. If a response is not a recognised paginated/list payload, do not tag it. The shared table should append the audit action only when at least one row in the current page has an audit id, so unaudited registers do not gain an empty column.

Keep the visual language restrained: a 3 px inline-start rail (logical property, RTL-safe), a small event chip/icon and a legend, with amber tint only on mapped changed cells. Use theme success/warning/error tokens, not fixed hex colours. Native `title` alone is not sufficient; add `aria-label`/`tabindex` and a visible tooltip or an accessible summary for changed cells. Reuse the existing audit translation keys and `formatValue`-style formatting; do not create a parallel formatter. `tabler-history` (or the chosen rail icon) must appear as a static full token so `npm run build:icons` bundles it; never edit `icons.css` or the allowlist for a static token.

For the five raw-table pages, migrate the register table to the shared wrapper when the wrapper supports its columns; otherwise use the same `useAuditTrail` composable, row-state helper and `AuditTrailButton` so the visual and permission semantics cannot drift. Preserve the existing table's filters, print rows, buttons and mobile behaviour. Do not add a deleted-row query parameter merely to demonstrate red; explicitly report the existing deleted-row visibility as a limitation and leave a follow-up decision.

## Risks and rollback
- Risk: tagging every Axios list response accidentally applies rails to reports, select options or unrelated arrays. Mitigation: require an exact list-route match and a paginated/list shape, default to no tag, and add a source check for every map entry.
- Risk: the shared table appends a column that collides with a page's own action column or breaks sticky/scroll/mobile layouts. Mitigation: make the audit column opt-in by data presence, preserve the existing action column, test wide and mobile tables, and provide a page-level opt-out if a register cannot accommodate it.
- Risk: N+1 label resolution for 100 records makes the register slow. Mitigation: one grouped latest-id query, bounded eager loads, a query-budget test and a short-lived per-request presenter cache.
- Risk: colour-only meaning is inaccessible or misleading in dark/RTL. Mitigation: pair every state with an icon/label, use logical CSS, and test both themes/directions.
- Risk: team-scoped audit data leaks through a record id. Mitigation: reuse the existing actor scope and test a reviewer whose employee scope excludes the requested record.
- Risk: an unavailable audit service blocks the register. Mitigation: enrichment is quiet and non-blocking; failure degrades to the existing table, with no toast or retry loop.
- Data migration: none. Rollback removes the route/map/table enhancement; audit rows and business data are untouched.
- Stop condition: if the latest audit cannot be resolved without unbounded queries, or if the existing Audits deep link cannot open an off-page audit, stop and report the contract gap rather than adding a second viewer or per-row fetches.

## Handoff checklist
- [ ] Contract and file ownership respected; the five forward ownership transfers are recorded in the old task files and `INDEX.md`.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`, including permission, scope, query-budget and viewport evidence.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Coverage matrix is checked against the current `AuditRegistry` and includes all 57 registry keys plus `account_deactivations` and `audit_settings`.
- [ ] Relevant audit/table docs and observability notes are updated if behaviour changes; no generated file is hand-edited.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit, especially deleted-row visibility and any page that could not adopt the shared table.
- [ ] Task is ready for review/integration, not merely code complete.