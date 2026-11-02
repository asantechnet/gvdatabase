# Task Task-021 — Give employees multiple department memberships without breaking existing HR behavior

## Metadata
- Task ID: `Task-021`
- Title: `Give employees multiple department memberships without breaking existing HR behavior`
- Initial status: `Ready`
- Type: `Backend`
- Priority: `P1`
- Owner role: `Laravel HR domain implementer with schema review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-01: one employee/user can belong to multiple departments; repository inspection 2026-11-01`
- Requirement / behavior IDs: `ORG-DEPT-001`, `ORG-DEPT-002`, `ORG-SCOPE-001`

## Objective
An employee can belong to several departments while the existing single `employees.department_id` value, supervisor scope, department manager scope, notifications and seeded data keep working.

## Context and problem
`employees.department_id` is the only department link today. The staff form has one "Department" select, `Department::employees()` is a `hasMany`, and `EmployeeScope` discovers a managed department's members with `where('department_id', ...)`. Payroll, notifications, payslips and report placeholders also read the single `department` relation. The requirement is one employee/user in multiple departments, but replacing the column outright would break every one of those readers and rewrite historical data.

The least destructive model is additive: keep `department_id` as the primary department, add a `department_employee` membership table, and make the primary value a projection of one membership. Existing readers continue to see one department, while new readers can see all memberships.

There is also hidden behavior that later tasks must surface. Department membership is not permission; being in a department grants nothing by itself. A department manager sees members through `EmployeeScope` only when their role holds `view_subordinate_records`, and the supervisor chain (`employees.supervisor_id`) is a separate reporting line that is unioned with managed-department membership. This task must preserve that exact split, not merge it.

## Scope
### Included
- Add a `department_employee` pivot with `employee_id`, `department_id`, `is_primary`, timestamps, a unique employee/department pair and query indexes.
- Backfill one primary membership for every non-null `employees.department_id`, idempotently.
- Add `Employee::departments()` and `Department::employees()` as `belongsToMany` relations while keeping `Employee::department()` for the primary department.
- Add one canonical writer, `Employee::syncDepartments(array $departmentIds, ?int $primaryDepartmentId = null)`, that validates membership, writes the pivot and keeps the legacy primary columns aligned inside a transaction.
- Keep legacy writes safe: setting `employees.department_id` directly still attaches a primary membership and never removes an employee's other memberships.
- Accept `department_ids` and `primary_department_id` on employee create/update alongside the legacy `department_id`; include `departments`, `department_ids` and `primary_department_id` in the employee resource.
- Include the new fields in the hierarchy-write guard so a caller without `assign_employee_supervisor` cannot add or remove memberships by using the new fields.
- Make `EmployeeScope` read every membership, while leaving the primary department as the value used by notifications and one-department consumers.
- Eager-load departments where the employee resource is returned; seed data is covered by the migration backfill and the legacy save hook, so no seeder edit is needed.
- Add tests for dual membership, legacy writes, primary fallback, empty membership, guard behaviour and scope discovery.

### Excluded
- Department page member management and the department API member contract; those are owned by Task-022 and Task-023.
- Staff directory form, table and the Reports-to confirmation; those are owned by Task-024.
- Role seniority level editing; that is owned by Task-025.
- Locale copy and business error translations; those are owned by Task-026.
- Dropping `employees.department_id`, changing notification routing, adding permissions, or moving payroll/contract data between departments.
- Treating a department membership as a permission or as a second supervisor chain.

## Preconditions and assumptions
- Departments use soft deletes. The pivot foreign key may cascade on a hard delete, but a soft-deleted department remains a data-quality case; this task does not rewrite historical memberships.
- Existing `department_id` readers must continue to work unchanged. No migration in this task drops or renames a column.
- `RoleHierarchy` remains the only seniority comparison and `EmployeeScope` remains the only org-scope resolver. Do not add a second rank or scope rule.
- Assumption to validate with the product owner: the "primary department" is `employees.department_id`, used where one department is required. If a membership set exists without an explicit primary, the oldest membership by pivot id becomes primary. If the last primary membership is removed, the oldest remaining membership is promoted; if none remain, the primary columns become null.
- Assumption to validate: an employee may have zero departments, matching the current nullable column. If the product requires at least one, stop and add that rule to both the backend and the staff form before implementation.
- The application supports MySQL and SQLite test databases. Keep the migration portable and do not rely on database-specific JSON functions.
- Existing tests create employees with `department_id` through the factory. The legacy save path must create the pivot row so those fixtures continue to satisfy scope tests.
- Seed data is covered by the backfill and the legacy save path. Task-009 owns the `database/seeders` directory, so this task does not edit a seeder; a future direct-insert seeder that bypasses the model is a follow-up.

## Dependencies
- `None` — this is the additive foundation. Its contract must be agreed before the department API or staff form consumes it.

## Contracts and boundaries
- Schema: `department_employee` has an auto-increment `id`, `employee_id`, `department_id`, boolean `is_primary` default false, timestamps, a unique pair and indexes on both foreign keys. A partial unique index for one primary per employee is preferred where the database supports it; otherwise enforce it inside the transaction.
- Relations: `Employee::departments()` returns every membership ordered by `is_primary` descending then name; `Department::employees()` returns every member; `Employee::department()` remains the primary legacy relation.
- Writer: `Employee::syncDepartments(array $departmentIds, ?int $primaryDepartmentId = null)` deduplicates ids, rejects soft-deleted departments, writes the exact membership set, marks exactly one primary, updates `department_id` and the legacy department name column, and returns the resulting collection. It must run inside a transaction and must not dispatch unrelated model events twice.
- Legacy write: when `department_id` is dirty and the employee is saved, attach that department and mark it primary without detaching other memberships. This keeps existing forms and tests working during the staged rollout.
- API request: `department_ids` is an optional array of distinct department ids; `primary_department_id` is optional and must be one of the submitted ids; the legacy `department_id` remains accepted. If `department_ids` is absent, a legacy `department_id` attaches or promotes a primary membership but does not detach others. If `department_ids` is present, it is authoritative for the full membership set, including an empty array.
- API response: an employee includes `departments: [{id, name, code}]`, `department_ids: number[]`, `primary_department_id: number|null`, plus the existing `department` object and `department_name` for the primary. `department_id` remains the primary id so old clients keep reading one value.
- Error behaviour: an unknown, soft-deleted or duplicate department id returns 422 on `department_ids` or the specific index. A primary outside the submitted set returns 422 on `primary_department_id`. A caller without `assign_employee_supervisor` has the hierarchy fields ignored exactly as `supervisor_id` is ignored today; no membership change is claimed by the response.
- Permission/data scope: employee update still requires the existing employee-edit permission; assigning departments or a supervisor additionally requires `assign_employee_supervisor`. Reading memberships follows the existing employee-read permissions and `EmployeeScope`.
- Integration seam: Task-022 calls this writer from the department controller; Task-023 and Task-024 read the response fields. Do not duplicate membership writes in a controller or a Vue page.
- Files this task must not modify: `app/Http/Controllers/DepartmentController.php`, `resources/ts/pages/setup/departments.vue`, `resources/ts/pages/staff_list.vue`, `app/Http/Controllers/RoleController.php`, `resources/ts/plugins/i18n/locales/**`.

## Exclusive file ownership
- `database/migrations/2026_11_01_000005_create_department_employee_table.php` — additive pivot and backfill.
- `app/Models/Employee.php` — membership relations, writer, primary projection and legacy save path.
- `app/Models/Department.php` — members relation and membership helpers.
- `app/Support/EmployeeScope.php` — scope through every membership.
- `app/Http/Resources/EmployeeResource.php` — departments, ids and primary fields.
- `app/Http/Controllers/EmployeeController.php` — validation, guard and eager loading.
- `tests/Feature/EmployeeDepartmentMembershipTest.php` — new membership contract coverage.
- `tests/Feature/RoleHierarchyScopeTest.php` — regression coverage for department-manager scope after the relation change.

## Acceptance criteria
- [ ] Given an employee added to Operations and Finance with Finance as primary, when the employee is fetched, then `department_ids` contains both ids, `departments` contains both names, `primary_department_id` and `department_id` are Finance, and `department_name` is Finance.
- [ ] Given an employee already in Operations, when a legacy update sends only `department_id` for Finance, then the employee remains in Operations and becomes primary in Finance; no other membership is detached.
- [ ] Given an update sends `department_ids` with two departments and an explicit primary, when it is saved, then exactly two pivot rows exist, exactly one is primary, and the legacy primary columns match.
- [ ] Given an update sends `department_ids: []`, when it is saved, then every membership is removed, `department_id` and `department_name` are null, and no orphan pivot rows remain.
- [ ] Given a department manager with `view_subordinate_records`, when an employee joins their department as a secondary membership, then that employee appears in the manager's `managedDepartmentMemberIds` without changing the supervisor chain.
- [ ] Given a caller without `assign_employee_supervisor`, when they submit `department_ids`, `primary_department_id` or `supervisor_id`, then those fields are ignored, no membership changes, and the response reflects the stored values rather than the submitted ones.
- [ ] Given an unknown or soft-deleted department id, when it is submitted, then the request returns 422 on the specific field or index and the existing memberships are unchanged.
- [ ] Given the migration runs against existing data with non-null `department_id`, when it is run twice, then every such employee has exactly one primary membership and the second run makes no duplicate rows.
- [ ] Given the existing employee, department-manager and supervisor-scope suites, when they run after the relation change, then no existing scope, delete-refusal or manager-option assertion regresses.

## Verification plan
- Automated: `php artisan migrate` against a disposable database; `php artisan test --filter=EmployeeDepartmentMembershipTest`; `php artisan test --filter=RoleHierarchyScopeTest`; `php artisan test --filter=DepartmentManagerTest` as a cross-contract regression even though Task-022 owns that file's forward changes.
- Database assertions: count pivot rows for a dual-membership employee; assert a unique primary per employee; rerun the backfill and assert the count is unchanged.
- API checks: create and update an employee with `department_ids`; send legacy `department_id` alone; send an unknown id; send an empty set; verify the resource shape and unchanged values for a caller without the hierarchy permission.
- Manual: confirm a department manager's subordinate list includes a secondary-department member; confirm a notification or payslip that reads the primary department still resolves one value; confirm an employee with no department still loads.
- Fixtures: two departments, one manager with `view_subordinate_records`, one member with a supervisor, one employee with no user account. Non-production only.
- Evidence: migration output, test names and assertion counts, a pivot-row query result, resource JSON for one dual-membership employee, and the regression suite result.

## Implementation notes
Implement the pivot and backfill first, then add the relations, then the canonical writer, then switch `EmployeeScope` to a membership subquery. Use `whereHas('departments', fn ($q) => $q->whereKey($departmentId))` or an equivalent membership join, not a JSON list. In the resource, compute `department_ids` from the loaded relation and fall back to the primary id only when the relation is not loaded, so employee endpoints never emit an empty list for a legacy row. Keep the legacy column write inside `syncDepartments` with `saveQuietly` after the pivot write to avoid a second `saved` hook. Update the employee index/show/store/update queries to eager-load `departments`. Do not add a route or a request class unless the existing controller cannot validate the new fields cleanly; prefer form-request rules in the existing controller for a smaller diff.

## Risks and rollback
- Risk: a relation change breaks a `hasMany` call site outside this task. Mitigation: search every `->employees` and `department_id` usage before changing the relation; run the department, employee and payroll suites; keep the legacy relation and column.
- Risk: dual-write drift between the pivot and `department_id`. Mitigation: one writer, a transaction, a database assertion in tests, and a repair command only if the task finds existing drift.
- Risk: a manager with an existing invalid member becomes unable to edit the department. Mitigation: this task validates new writes only; Task-022 owns the member API and decides whether to validate the whole resulting set.
- Data migration: additive only. The migration down drops the pivot; the legacy columns and all existing data remain. Take a database backup before running the backfill on production.
- Stop condition: if any consumer requires more than one primary department, or if the product rejects the primary-department projection, stop before the migration and record the decision instead of inventing a second primary rule.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs, audit notes and the `docs/facts` HR description are updated if behaviour changed.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
