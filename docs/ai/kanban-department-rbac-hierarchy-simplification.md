## Task: Kanban/department RBAC and organisation-hierarchy simplification

- **Task ID:** kanban-department-rbac-hierarchy-simplification
- **Created:** 2026-11-09
- **Last updated:** 2026-11-09
- **Status:** COMPLETE
- **Outcome:** Make task/board permissions explicit, add department task boards with own-department task management, add inline personal task creation and comment mentions/notifications, and simplify roles/departments to senior/junior with multiple department managers and no reports-to or primary-department concept.

### 1. Requirements and acceptance criteria
- [x] A dedicated `view_kanban` permission controls the Kanban tab and `/api/kanban`.
- [x] A dedicated `update_kanban` permission controls whether a user may change shared-board tasks; personal-board CRUD remains governed by `create_personal_tasks`.
- [x] The task matrix is labelled “Tasks”, and it can create a task that is saved to the caller's Personal board.
- [x] Roles expose only Senior/Junior seniority; a department may have multiple managers; only senior-role employees are eligible.
- [x] `employees.supervisor_id`, `employees.department_id`, `employees.department`, and `department_employee.is_primary` are retired; scope uses department membership + seniority.
- [x] Creating a department optionally creates its task board (checked by default) and stores `boards.department_id`.
- [x] A department manager with `manage_department_tasks` can CRUD tasks on that department's board and may assign only department members.
- [x] A comment on a task notifies its assignees; `@` mentions list assignees + task creator, render distinctly, and notify mentioned users.
- [x] Existing leave-request naming/pagination and plural case responsibility remain unchanged; finance visibility is explicitly out of scope.

### 2. Scope and non-goals
- In scope: migrations, models, resources, controllers, policies, permissions, seeders, task/board APIs, task matrix UI, comment drawer/API, role/department/staff UI and types, relevant tests.
- Out of scope: explicit finance-data permission (user deferred it), websocket notifications, mutation offline queue, redesigning contracts/payroll.

### 3. Project findings
- Boards are currently product-owned; `BoardController` has no write path and `boards` has no department link.
- `view_all_boards`/`view_assigned_boards` exist, but there is no `view_kanban` or `update_kanban`.
- The task matrix (`TodoMatrixView.vue`) only creates subtasks; it has no parent-task creation.
- Task comments have no mention storage, no mention UI, and `CommentController` sends no notifications.
- `RoleHierarchy` ranks numeric `roles.level`; `DepartmentController` restricts managers by rank and only supports one `manager_employee_id`.
- `EmployeeScope` uses `employees.supervisor_id` plus department management; `employees.department_id`/pivot `is_primary` project a primary department.
- Leave requests are already renamed and paginated (`LeaveRequestApi.listPage`, `ResponsiveDataTableServer`).
- Case responsibility is already plural via `responsible_employee_ids`.

### 4. Graph and impact review
- Graph skill unavailable in this session; used repository-wide `code_search`, `glob`, targeted `read_files`, controller/model/resource tracing, and route inspection.
- Impacted callers: `EmployeeController`, `DepartmentController`, `DepartmentMembership`, `EmployeeScope`, `RoleHierarchy`, `EducationalWorkflowNotificationService`, `AnnouncementService`, `CommissionSlipService`, `SalaryController`, `Board`, `Task`, `TaskAccess`, `TaskPolicy`, `TaskController`, `KanbanController`, `BoardController`, `CommentController`, `PermissionSeeder`, `RolePermissionSeeder`, frontend task/store/API/UI, staff/department/role pages.
- Tests needing updates: DepartmentManagerTest, RoleHierarchyScopeTest, DepartmentTaskAssignmentTest, DepartmentMembershipTest, EmployeeDepartmentMembershipTest.

### 5. Clarifications, decisions, and assumptions
- **User decisions:** remove reports-to/main department fully; senior/junior roles with multiple department managers; department board checkbox default on; global `view_kanban`/`update_kanban` permissions; finance task omitted.
- **Assumption:** `roles.level` remains as a deprecated internal column for compatibility, but the UI/API/authorisation use `roles.is_senior`. `departments.manager_employee_id` remains as the first-manager projection for compatibility; `department_manager` is the source of truth for multiple managers.
- **Assumption:** department board title is `"{department name} Tasks"` to avoid colliding with service boards.

### 6. Implementation plan
- [x] Add one migration for `roles.is_senior`, `department_manager`, `boards.department_id`, `tasks.created_by`, `comment_mentions`, backfills, and retirement of supervisor/primary-department columns.
- [x] Rewrite role/department scope: `RoleHierarchy`, `EmployeeScope`, `DepartmentMembership`, `DepartmentController`, `EmployeeController`, resources, seeders, role assignment guard.
- [x] Add Kanban/manage-department permissions and enforce them in routes, `TaskAccess`, `TaskPolicy`, `TaskController`.
- [x] Add department board creation/visibility and department manager sync.
- [x] Add task creator, comment mentions pivot, comment notifications, and mention resources/API.
- [x] Add inline personal task creation and rename the matrix in the frontend.
- [x] Add comment mention typeahead/styling and send mention ids.
- [x] Update department/role/staff UI types and forms.
- [x] Update affected feature tests and run focused PHP tests + `npm run typecheck`.

### 7. Validation plan and results
- [x] `php -l` changed PHP files — passed.
- [x] Focused PHP feature tests for departments, hierarchy, task assignment, comments — `28 passed (110 assertions)`.
- [x] New comment mention/notification coverage — `1 passed (6 assertions)` in `tests/Feature/TaskCommentMentionTest.php`.
- [x] `npm run typecheck` — exited 0.
- [x] Playwright CLI login and UI checks — verified the Tasks label, inline personal task creation, the @ picker listing Demo Admin, a sent comment rendering with `.comment-mention`, Senior/Junior role controls, multi-manager department creation with the default board switch, a staff page without reports-to/primary-department fields, and Leave Requests in both the sidebar and page heading.
- Migration `2026_11_09_100000_simplify_org_hierarchy_and_department_boards.php` applied successfully to `educational_office_main`; isolated tests run against `testing_buffy`.
- Results: all implemented and verified.

### 8. Risks and mitigations
- Dropping hierarchy columns can break old code/tests — mitigation: exhaustive reference search and focused updates; migration backfills memberships before dropping.
- Existing role levels/custom levels — mitigation: backfill `is_senior` from level threshold and expose the toggle for correction.
- Notification noise — mitigation: generic assignee notification excludes mentioned users; each user gets one comment/mention notice per comment.
- Board title collisions with service boards — mitigation: department boards keyed by `department_id`; service lookup restricted to `department_id IS NULL`.

### 9. Progress log
- 2026-11-09 — DISCOVERY: reviewed existing task/board/comment/role/department code; confirmed case responsibility, leave renaming and leave pagination already delivered.
- 2026-11-09 — CLARIFICATION: recorded user choices for full hierarchy removal, senior/junior with many managers, default-on department board, global Kanban permissions, and finance omission.
- 2026-11-09 — PLAN: plan saved; implementation starting.
- 2026-11-09 — IMPLEMENTATION: added `roles.is_senior`, `department_manager`, `boards.department_id`, `tasks.created_by`, `comment_mentions`, backfills and retirement of supervisor/primary-department columns; wired global `view_kanban`/`update_kanban`, department boards, personal task creation, comment mentions and notifications, and Senior/Junior/multi-manager UI.
- 2026-11-09 — CLEANUP: removed the unused supervisor disclosure component and legacy supervisor/primary-department form state; renamed remaining user-facing off-day request labels to leave requests.
- 2026-11-09 — VALIDATION: migration applied to `educational_office_main`; focused tests `28 passed (110 assertions)`; new mention test `1 passed (6 assertions)`; `npm run typecheck` exited 0; Playwright verified Tasks/personal creation/mention rendering, role seniority, multi-manager department creation, staff page without reports-to, and Leave Requests naming.
