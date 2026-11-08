# Task Task-038 — Academy class, enrollment and overview API

## Metadata
- Task ID: `Task-038`
- Title: `Academy class, enrollment and overview API`
- Initial status: `Ready`
- Type: `API`
- Priority: `P1`
- Owner role: `Laravel API implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decisions: dedicated Academy section, rolling classes, one student with several courses, shallow attendance and certificates. Repository inspection of EducationalMasterDataController, academy models and routes/api.php on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-API-001`, `ACADEMY-API-002`, `ACADEMY-API-003`, `ACADEMY-API-004`

## Objective
Give the Academy workspace a stable API for classes, enrollments, duration months and overview KPIs. Reads are scoped by the existing academy permission; writes require explicit academy manage permissions. The existing contract creation, master-data catalogue and contract endpoints keep their current behaviour.

## Context and problem
The generic master-data controller can only edit a class name, description, display order and active flag. It has no class dates, capacity, teachers, weekdays, default duration or fee, and it maps class permission checks to the `program` permissions. There is no enrollment read endpoint and no academy overview data. The existing `/contracts/academy` page is a contract register, not an operational workspace.

This task adds dedicated academy endpoints instead of widening the shared catalogue controller, so Task-029's stage/catalogue ownership and Task-035's audit work are not disturbed. It owns `routes/api.php` for one inclusion point and creates a small academy route tree so later attendance and certificate tasks can add their own route files without editing a shared file.

## Scope
### Included
- Class CRUD with validation for name, programme, time, teachers, weekdays, start/end time, capacity, start/end date, default duration, fee and active state.
- In-use and capacity guards: a class with enrollments cannot be deleted; capacity cannot be reduced below the current active enrollment count.
- Enrollment list/show/status endpoint with filters for class, status, ending soon, search and teacher; paginated and eager-loaded.
- Enrollment status updates to `completed`, `withdrawn` or `cancelled` with `completed_on` and notes; no contract, line, stage or payment write.
- Overview endpoint with distinct active students, active enrollments, classes, seats filled/capacity, ending in 30 days, outstanding balance, completed this month and today's classes.
- Duration months read/update endpoint for the Academy catalogue page.
- Route registration in `routes/api.php` plus an academy route tree that auto-loads domain route files. Add one guarded include for `routes/crm.php` when it exists; Task-047 owns that CRM route file and this task adds no CRM route or controller.
- Academy read/manage permissions and role assignments.
- Rename the singular admission-assignment permission to `assign_responsible_employees` in place and preserve every existing role grant; Task-046 consumes the new name.
- Feature tests for validation, capacity, filters, overview math, permissions and route registration.

### Excluded
- Contract creation, end-date computation or capacity enforcement during a sale. Task-037 owns those.
- UI, navigation, API clients, locale copy and print. Task-039 through Task-044 own those.
- Attendance and certificate tables or endpoints. Task-040 and Task-041 own those.
- Editing `EducationalMasterDataController`, `routes.ts`, `ContractResource`, `ContractController` or any Vue file.
- Changing fee, payment, stage or contract status semantics.
- Deleting or moving academy catalogue rows.

## Preconditions and assumptions
- Task-036 has created `academy_enrollments`, class fields and duration months.
- Task-036 creates the enrollment schema; this task seeds enrollment rows for its tests and does not depend on Task-037's contract-generation work. Task-037 consumes this task's renamed permission.
- The existing `view_academy_contracts` permission is the read gate; the new manage permissions are added by this task and granted to the appropriate seeded roles.
- `applicant_contract` lines carry the fee, discount, paid amount and currency used for the balance shown in the enrollment resource.
- A class can be open-ended and unlimited; both are nullable.
- The app is served under `/api`; the route loader is included inside the authenticated API group.
- No frontend client exists yet, so this task verifies through HTTP tests, not a browser.

## Dependencies
- `Task-035` — owns `routes/api.php`; forward ownership of that file moves to this task on 2026-11-07. Task-035's audit route behaviour must remain intact.
- `Task-036` — provides the enrollment table, class fields, duration months and models.

The renamed permission is consumed downstream; the API tests create their own enrollment fixtures.

## Contracts and boundaries
- Read gate: `view_academy_contracts` on every GET endpoint. Write gates: `manage_academy_classes` for class and catalogue writes, `manage_academy_attendance` for attendance later, `issue_academy_certificates` for certificates later. Super admin retains its existing bypass.
- Class payload: `name`, `description`, `program_id`, `time_id`, `teacher_ids`, `weekdays`, `starts_at`, `ends_at`, `capacity`, `starts_on`, `ends_on`, `duration_id`, `fee`, `is_active`, `sort_order`. `weekdays` is an array of distinct integers 1-7. `capacity` is null or 1-10000. `fee` is null or a non-negative decimal.
- Enrollment payload: `id`, `student_id`, `applicant` (id/name/contact/father phone), `contract` (id/number/status/start/end), `line` (id/fee/discount/paid/balance/payment status/currency), `class` (id/name/program/time/teachers/capacity/seats taken), `duration`, `started_on`, `ends_on`, `status`, `completed_on`, `notes`.
- Enrollment status transition: `active` to `completed`, `withdrawn` or `cancelled`; completed sets `completed_on` to today; terminal rows cannot return to active without a separate correction decision. No contract or line field is written.
- Overview math: active students = distinct `applicant_id` with `active` enrollments; active enrollments = status `active`; seats filled = active enrollments per class; ending soon = active with `ends_on` between today and today + 30 days; outstanding = max(0, line fee - discount - amount paid) in the line currency; completed this month = `completed_on` in the current month. Today's classes = active classes whose weekdays include today, ordered by start time.
- Routes: literal paths are registered before any `{id}` resource route. `/academy/durations` is read/update only; the generic master-data route stays unchanged.
- Route tree: `routes/api.php` requires `routes/academy.php`; that file requires every PHP file in `routes/academy/`. Task-040 and Task-041 add their own files without editing the umbrella or `routes/api.php`. `routes/api.php` also requires `routes/crm.php` only when that file exists, so Task-047's relationship routes become active without a second edit to the shared API file; no CRM route is added here.
- Compatibility guarantee: existing `GET/POST/PUT /contracts/academy*`, `/educational-master-data/*` and the contract/slip resources are unchanged.
- Files this task must not modify: `app/Http/Controllers/EducationalMasterDataController.php`, `app/Http/Controllers/ContractController.php`, `app/Http/Resources/ContractResource.php`, `resources/ts/**`, `resources/ts/plugins/i18n/locales/**`, `lang/**`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`.

## Exclusive file ownership
- `routes/api.php` — one inclusion point for the academy route tree; forward ownership from Task-035.
- `routes/academy.php` — academy route-tree loader that requires every domain file in `routes/academy/`.
- `routes/academy/classes.php` — class CRUD routes.
- `routes/academy/enrollments.php` — enrollment reads and status updates.
- `routes/academy/overview.php` — overview route.
- `routes/academy/catalog.php` — duration months reads/updates.
- `app/Http/Controllers/AcademyClassController.php` — class CRUD, validation, in-use and capacity guards.
- `app/Http/Controllers/AcademyEnrollmentController.php` — scoped enrollment list/show/status.
- `app/Http/Controllers/AcademyOverviewController.php` — KPI and today's-classes read.
- `app/Http/Controllers/AcademyCatalogController.php` — duration months read/update.
- `app/Http/Requests/StoreAcademyClassRequest.php` — create validation.
- `app/Http/Requests/UpdateAcademyClassRequest.php` — update validation and capacity floor.
- `app/Http/Requests/UpdateAcademyEnrollmentRequest.php` — status/notes/completion validation.
- `app/Http/Resources/AcademyClassResource.php` — class payload with seats taken and teachers.
- `app/Http/Resources/AcademyEnrollmentResource.php` — enrollment payload with applicant, contract, line, class and balance.
- `database/seeders/PermissionSeeder.php` — three academy manage permissions.
- `database/seeders/RolePermissionSeeder.php` — role grants for the new permissions and the responsible-employee rename.
- `database/migrations/2026_11_07_000009_rename_admission_permission.php` — in-place permission rename and role-grant preservation.
- `tests/Feature/AcademyClassApiTest.php` — class CRUD, validation, delete and capacity guards.
- `tests/Feature/AcademyEnrollmentApiTest.php` — filters, scope, payload and status transitions.
- `tests/Feature/AcademyOverviewApiTest.php` — KPI math and today's classes.
- `tests/Feature/AcademyPermissionTest.php` — read/write permission matrix and route registration.

## Acceptance criteria
- [ ] Given a viewer with `view_academy_contracts`, when they GET the class, enrollment and overview endpoints, then they receive paginated or filtered data with class, programme, time, teachers, dates, capacity, seats taken and balance, and the endpoint adds no N+1 query beyond the documented eager loads.
- [ ] Given a manager with `manage_academy_classes`, when they create or update a class, then validation covers name, programme/time, teachers, weekdays 1-7, start/end times, capacity, dates, duration, fee and active state; invalid values return field errors and no row.
- [ ] Given a class that already has enrollments, when a delete is attempted, then it is refused with a specific business code and no rows are removed; when capacity is reduced below the current active count, the update is refused.
- [ ] Given the enrollment filters `class_id`, `status`, `ending`, `search` and `teacher_id`, when applied, then the list returns only matching rows, respects the caller's academy scope, and keeps pagination and sorting.
- [ ] Given an enrollment update to completed, withdrawn or cancelled, then only the enrollment status, `completed_on` and notes change; the contract, line, stage, payment state and issued documents do not change; an invalid transition is refused.
- [ ] Given the overview endpoint, when the data is summed, then active students are distinct applicants with active enrollments, seats filled counts active enrollments only, ending-in-30-days uses `ends_on`, outstanding uses line payable minus paid, and completed-this-month uses `completed_on`; the numbers match focused fixtures.
- [ ] Given a viewer without `view_academy_contracts`, when any academy read endpoint is called, then the response is 403; given a viewer with read but without the relevant manage permission, when a write is attempted, then the response is 403; super admin and granted roles pass.
- [ ] Given `PATCH /academy/durations/{duration}`, when months is 1-60 or null, then it saves; zero, negative or greater-than-60 is rejected; the route is registered before any resource binding and the existing master-data endpoints are unchanged.
- [ ] Given the focused suites, when `php artisan test --filter=AcademyClassApiTest`, `php artisan test --filter=AcademyEnrollmentApiTest`, `php artisan test --filter=AcademyOverviewApiTest` and `php artisan test --filter=AcademyPermissionTest` run, then they pass.

- [ ] Given the permission catalogue and an existing database, when the migration and seeders run, then `assign_admission_employee` is renamed in place to `assign_responsible_employees`, every role that held the old permission still holds the new one, no other grant changes, and a fresh seed creates the new name.

## Verification plan
- Automated: run the four focused suites on an isolated database; assert JSON shapes, validation fields, status codes and query counts with `DB::enableQueryLog` or a query-count helper.
- Route check: `php artisan route:list --path=academy` shows every route once, under auth, with the intended permission; assert `/academy/durations` does not collide with a resource route.
- Permission matrix: create roles/users with only read, only manage classes, read plus manage classes, and no academy permission; exercise GET/POST/PUT/PATCH/DELETE and assert 200/403 and no writes on refusal.
- Overview fixtures: two applications with three active enrollments, one completed this month, one ending in 20 days, one ending in 60 days and one withdrawn; assert each KPI exactly.
- Capacity guard: create four active enrollments in a capacity-4 class, then attempt capacity 3; assert refusal and unchanged capacity.
- Regression: run the contract creation, stage catalogue and audit-trail suites; confirm `routes/api.php` still serves the audit routes and the academy contract routes.
- Evidence: route-list output, test output, permission matrix table and overview fixture numbers.

## Implementation notes
Keep the controllers thin and reuse the enrollment/class models. Use `Rule::exists(...)->whereNull('deleted_at')` for catalogues. Build the enrollment query with `with(['applicant', 'contract', 'line.currency', 'class.program', 'class.time', 'class.teachers', 'duration'])` and a `withCount` for seats; do not fetch a roster in the list endpoint.

For the route tree, create `routes/academy.php` with a loop over `glob(__DIR__.'/academy/*.php')` and `require` each file. In `routes/api.php`, add one `require base_path('routes/academy.php')` inside the existing authenticated API group, before the contract routes. Each domain file starts its own `Route::prefix('academy')` group and applies its own middleware. This is deliberately a small convention so Task-040 and Task-041 can add files without a shared-file edit.

Add the guarded CRM handoff in the same authenticated group: `if (is_file(base_path('routes/crm.php'))) { require base_path('routes/crm.php'); }`. The guard keeps this task independently implementable before Task-047 creates its file; do not create, edit or route any CRM controller here.

Add the permissions to the seeder and role seeder without renaming existing permissions. Do not add `view_academy_workspace`; use `view_academy_contracts` for reads. If a role that can already manage programmes should also manage classes, grant `manage_academy_classes` explicitly rather than reusing `program`.

## Risks and rollback
- Risk: the route auto-loader changes route order or caching. Mitigation: assert `route:list`, run `route:cache` in a test environment and keep the academy group after public/auth routes but before resource bindings that could swallow `academy`.
- Risk: the overview query becomes expensive as enrollments grow. Mitigation: indexed `class_id/status/ends_on/completed_on`, aggregate SQL rather than loading rows, and a query-count assertion.
- Risk: a role loses access because the read gate differs from the contract page. Mitigation: use `view_academy_contracts` consistently and record the matrix in the task evidence.
- Risk: reducing class capacity strands active students. Mitigation: refuse below the active count; no automatic moves.
- Rollback: remove the academy route include and the new files; the additive tables and permissions can remain. Do not delete enrollment rows or revert class data.
- Stop condition: if the existing role catalogue has no role that should receive the manage permissions, stop and ask the product owner rather than granting to every role.

## Handoff checklist
- [ ] Contract and file ownership respected, including the `routes/api.php` handoff from Task-035.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] API shapes and permission names are documented for Task-039 and Task-043.
- [ ] The route-tree convention is recorded for Task-040 and Task-041, including the guarded `routes/crm.php` include for Task-047.
- [ ] Remaining limitations and follow-up tasks are explicit.
