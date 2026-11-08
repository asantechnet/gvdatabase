# Task Task-039 — Build the Academy overview, classes, students and catalogue pages

## Metadata
- Task ID: `Task-039`
- Title: `Build the Academy overview, classes, students and catalogue pages`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `Vue 3 frontend implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decisions: separate Academy section, rolling classes, several courses per student, current slip/contract preserved. Repository inspection of academy-data.vue, service-contracts.vue, ErpPageHeader and the shared table on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-UI-001`, `ACADEMY-UI-002`, `ACADEMY-UI-003`, `ACADEMY-UI-004`

## Objective
Build the operational Academy pages: an overview with real KPIs, a class register with dates/seats/roster, a student register that shows one row per enrollment and groups multiple courses by student, and an academy catalogue that replaces the old Options → Contracts entry point. The existing Contracts → Academy creation page stays the only way to sell an academy contract.

## Context and problem
Academy currently appears only as a generic contract register and a small catalogue page under Options → Contracts. The register shows no class, term or teacher; the catalogue edits names only. There is no overview, no roster, no student card and no way to see a returning student's several courses. The client wants the academy to feel like a real section of the ERP while remaining a side business, so the pages must be operational, not a full LMS.

This task consumes the API from Task-038 and the classes/enrollments from Task-036 and Task-037. Routes and top-level navigation are deliberately owned by Task-044 so three page tasks do not edit the same router or nav file. Until Task-044 lands, this task verifies source, typecheck and API shapes; browser evidence is recorded by Task-044/Task-045.

## Scope
### Included
- Academy overview page with active students, active classes, seats filled, ending in 30 days, outstanding balance, completed this month and today's classes; loading, empty and error states.
- Classes page with a rich create/edit dialog (programme, time, teachers, weekdays, start/end time, capacity, start/end date, default duration, fee, active), seats taken, roster view and links to the student card and contract.
- Students page with one row per enrollment and filters for class, status, ending soon, search and teacher; a student card that groups all courses for the same student and shows each course's class, dates, status and balance.
- Academy catalogue page for programmes, times, durations (including months), teachers and a link to the Classes page; it becomes the Academy section's catalogue and the old route redirects here when Task-044 wires it.
- Typed API clients for classes, enrollments and overview.
- Reuse of the shared formatter, table/card primitives, `ErpPageHeader`, `vue-toastification` success feedback and the global API notifier.
- Responsive/RTL/dark/accessibility treatment and missing-key placement under the frozen academy namespace.

### Excluded
- Creating or editing contracts, end-date or capacity logic, and any change to `service-contracts.vue`, `ContractForm.vue`, `ApplicantServiceFields.vue` or `apiValidation.ts`. The existing creation page is unchanged.
- Routes, top-level navigation and section tabs. Task-044 owns those.
- Attendance and certificate pages. Task-040 and Task-041 own those.
- Editing the old `academy-data.vue` page. It remains for audit; the new catalogue is a separate page.
- New locale files or direct edits to the three locale catalogues. Task-043 owns the copy; this task uses the frozen keys and records any missing key.
- A timetable generator, waitlist, online payment, LMS content, gradebook or student portal expansion.

## Preconditions and assumptions
- Task-038 exposes the class, enrollment and overview endpoints, including seats taken and the enrollment balance.
- Task-037 creates one enrollment per academy line with term dates and capacity enforcement.
- The shared formatter (`resources/ts/utils/money.ts` and `localization.ts`) is available; do not format money/dates locally.
- The shared table/card primitives from Task-013 are available; use them instead of a second table implementation.
- `view_academy_contracts` is the read gate; `manage_academy_classes` is the class/catalogue write gate, and `issue_academy_certificates` / `manage_academy_attendance` are reserved for later pages.
- The frozen key namespace is `academy.*` with siblings under `common.navigation.academy`; Task-043 adds the values.
- The old `/contracts/academy` route and its page remain the sales entry point; this task does not change them.

## Dependencies
- `Task-037` — live enrollments and contract terms to display.
- `Task-038` — class, enrollment, overview and duration-months APIs and permission names.

## Contracts and boundaries
- Data source: only `AcademyClassesApi`, `AcademyEnrollmentsApi` and `AcademyOverviewApi` from this task. Do not read `applicantContracts` directly or duplicate the balance math in the page.
- Class dialog: one writer for create and edit; sends only the fields in the Task-038 class payload; capacity floor and in-use errors stay inline on the field. A delete is refused when the API says the class is in use.
- Students page: one row per enrollment, not one per applicant. The student card groups by `applicant_id`/`student_id` and shows every enrollment returned by the filtered list or a dedicated detail read; it must not merge fees, dates or statuses.
- Overview: read-only; no chart library, no new dependency. Use Vuetify cards/chips and the existing icons.
- Catalogue: programmes/times/teachers use the existing master-data endpoints; durations read/update through the Task-038 academy endpoint so months can be edited; classes link to the Classes page instead of duplicating the rich class form.
- Permissions: hide/disable a write control when the viewer lacks the manage permission; do not rely on a 403 after submit. Read controls use the route/UI gate.
- Error feedback: success uses one `vue-toastification` toast; API failures are left to the global notifier; local validation is inline.
- Compatibility: do not edit the old academy page, the contract register, the slip or any print template.
- Files this task must not modify: `resources/ts/plugins/1.router/routes.ts`, `resources/ts/navigation/**`, `resources/ts/pages/academy-data.vue`, `resources/ts/pages/service-contracts.vue`, `resources/ts/components/ContractForm.vue`, `resources/ts/components/ApplicantServiceFields.vue`, `resources/ts/utils/apiValidation.ts`, `resources/ts/plugins/i18n/locales/**`, `app/**`, `routes/**`, `lang/**`.

## Exclusive file ownership
- `resources/ts/pages/academy/overview.vue` — KPI cards, today's classes and quick links.
- `resources/ts/pages/academy/classes.vue` — class register, seats, roster and class actions.
- `resources/ts/pages/academy/students.vue` — enrollment register, filters and student card.
- `resources/ts/pages/academy/catalog.vue` — academy catalogue with duration months.
- `resources/ts/components/academy/AcademyClassDialog.vue` — rich class create/edit dialog.
- `resources/ts/components/academy/AcademyEnrollmentDialog.vue` — enrollment detail/status/notes dialog.
- `resources/ts/components/academy/AcademyStudentCard.vue` — grouped multi-course student view.
- `resources/ts/api/academyClasses.ts` — typed class list/show/create/update/delete and teacher options.
- `resources/ts/api/academyEnrollments.ts` — typed enrollment list/show/status update.
- `resources/ts/api/academyOverview.ts` — typed overview payload.

## Acceptance criteria
- [ ] Given the Academy routes are wired by Task-044, when the user opens Overview, then the KPI cards and today's classes render from the API with loading, empty and error states and no hard-coded values.
- [ ] Given the Classes page, when a class is created or edited, then the dialog captures programme, time, teachers, weekdays, start/end time, capacity, start/end date, default duration, fee and active state; it shows seats taken and keeps a capacity-floor refusal inline.
- [ ] Given the Classes page, when a row is opened, then it shows the roster with student, contact, start/end, status, balance and actions to open the student card and contract; an empty roster has a clear empty state.
- [ ] Given the Students page, when class, status, ending-soon, search and teacher filters are applied, then one row per enrollment is shown with student id, name, contact, class, teacher, start/end, status, fee/paid/balance; selecting a row opens the student card.
- [ ] Given a student with two academy courses, when the student card opens, then both enrollments appear with distinct classes, dates and contracts, and the card does not overwrite or merge their fees or statuses.
- [ ] Given the Catalogue page, when programmes, times, teachers and durations are edited, then the existing catalogue behaviour is preserved and duration months can be set 1-60 or cleared; the page is the Academy section's catalogue and does not duplicate the rich class form.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the pages render, then tables and cards do not overflow, icon actions have accessible names, focus order is preserved, and money/dates use the shared formatters.
- [ ] Given any request failure, when the page handles it, then the global API notifier owns the error toast, the page shows its local empty/error state, and no duplicate toast is emitted.
- [ ] Given the frontend checks, when `npm run typecheck` and `npm run i18n:check` run, then they pass; the task records that browser evidence waits for Task-044 to wire routes.

## Verification plan
- Automated: `npm run typecheck` and `npm run i18n:check`; run any existing frontend test harness that covers the shared table or money formatter.
- Source checks: confirm no page formats money/date locally, no page calls `/contracts` for academy reads, and every new class/enrollment field in the dialog maps to the Task-038 payload.
- API-shape checks: use the Task-038 test fixtures or a local API response to verify the typed clients unwrap `response.data.data` exactly once and handle null class/term/teacher values.
- Manual after Task-044: open `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog` at 390/768/1280 px, LTR/RTL, light/dark; create a class, add a second course to a student, filter ending-soon, open the student card and the roster; assert focus, labels and empty states.
- Permission check: a viewer with read but without `manage_academy_classes` sees no enabled class write action; a manager sees it; a direct unauthorized write is left to the API and Task-038's 403.
- Regression: service contract register, academy slip and the old academy catalogue still open; no route or nav file changed in this task's diff.
- Evidence: typecheck/i18n output, API-shaped fixtures, and the post-wiring screenshots recorded by Task-044.

## Implementation notes
Use the shared `ResponsiveDataTable`/card configuration rather than a raw `VTable` so the register inherits mobile cards, key columns and named actions. Keep the class dialog as one component with a create/edit mode; the roster belongs on the Classes page or a dialog opened from a class row, not in the create form.

The student card should fetch a small grouped read by applicant or use the filtered enrollment list already loaded; do not run a query per course. Show the student id once at the top, then one card/row per enrollment. Use the existing `ErpPageHeader` and icon names already bundled (`tabler-school`, `tabler-calendar-event`, `tabler-users`, `tabler-certificate`, `tabler-chart-bar`); run `npm run build:icons` only in the integration task if a new static icon name is introduced.

If a key is not yet in the locale catalogue, use the frozen key name and record it for Task-043; do not add an English fallback or edit the JSON file. Keep all copy in the page through `$t('academy...')`.

## Risks and rollback
- Risk: the workspace duplicates contract data and drifts from the finance ledger. Mitigation: show the line balance from the API only; never recompute paid amounts in the page.
- Risk: the student card groups by name and merges two different people. Mitigation: group by `applicant_id` and show `student_id`/contact as the identity; do not group by name alone.
- Risk: the rich class dialog becomes a second catalogue editor. Mitigation: classes only on the Classes page; the Catalogue page links to it.
- Risk: the pages cannot be browser-verified before Task-044. Mitigation: record the source/typecheck evidence now and make Task-044/Task-045 own the route-level browser evidence.
- Rollback: remove the new pages/components/clients; no schema, API or existing page is changed.
- Stop condition: if Task-038's enrollment payload lacks the line balance or class seats needed here, stop and return the dependency rather than computing it client-side.

## Handoff checklist
- [ ] Contract and file ownership respected; no route/nav/locale/old-page edit.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Frozen key names and any missing keys are listed for Task-043.
- [ ] Browser evidence is explicitly deferred to Task-044/Task-045.
- [ ] Remaining limitations and follow-up tasks are explicit.
