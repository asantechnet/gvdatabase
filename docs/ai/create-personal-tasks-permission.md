## Task: Create-personal-tasks permission with full CRUD on the owner's personal board

- **Task ID:** create-personal-tasks-permission
- **Created:** 2026-11-07
- **Last updated:** 2026-11-07
- **Status:** COMPLETED
- **Outcome:** A `create_personal_tasks` permission lets its holder create tasks on their own private Personal board and read, update, delete and reorder tasks there, without granting task actions on shared boards or another user's personal board. The permission is granted to every employee role by default.

### 1. Requirements and acceptance criteria

- [x] Add `create_personal_tasks` to the canonical permission catalog under `task_management`, with a seed migration for existing installations.
- [x] Grant `create_personal_tasks` by default to all employee roles (every role except `applicant`; `super_admin` already bypasses permission checks).
- [x] A holder can open the Tasks area and load the Kanban even when their role has no `view_assigned_boards`/`view_all_boards` grant.
- [x] A holder can create a task on their own personal board without `create_tasks`.
- [x] A holder can view every task on their own personal board, not only tasks where they are an assignee.
- [x] A holder can update, delete and reorder tasks on their own personal board without the general edit/delete/move task grants.
- [x] The permission never grants create/update/delete/move on a shared board or on another user's personal board.
- [x] The Personal board shows the add-task control and card edit/delete menu entries for a holder; shared boards remain gated by the existing permissions.
- [x] Saving a personal task from the task editor works even when the holder lacks `manage_task_dependencies`; dependency editing stays hidden and is not submitted.
- [x] Focused feature tests cover the default grants, allowed personal actions, denied shared/foreign actions, and board routes; typecheck has no new errors.

### 2. Scope and non-goals

- **In scope:** permission catalog, default role grants and seed migration; task/board policies and task visibility; Kanban board/task route access; frontend route/nav/API gates; Kanban add button, card menu, drag enablement, task store guards, and the edit drawer/detail drawer gates.
- **Out of scope:** creating/renaming/deleting board definitions (boards remain product-owned), comments/subtasks/attachments/tags/assignee-management permissions, calendar/Gantt/Todo Matrix drag behavior, notification delivery, and any change to shared-board permissions.

### 3. Project findings

- **Personal boards:** `Board::isPersonal()` is true when `user_id` is non-null. `Board::scopeVisibleTo` always includes the caller's own personal board, and `TaskAccess::canViewBoard` restricts personal boards strictly to their owner (even super admins cannot see another user's).
- **Creation gate:** `TaskPolicy::create` currently requires `create_tasks` plus board visibility. `TaskController::store` has no route permission middleware, so the policy is the security boundary. It defaults assignees to the creator.
- **Edit/delete/move gates:** `TaskPolicy` uses `edit_all_tasks`, `edit_assigned_tasks`, `delete_*` and `move_*`. The frontend repeats these checks in `KanbanCard`, `KanbanBoardEditDrawer`, `TaskDetailDrawer` and `useTaskStore`.
- **Board loading:** `KanbanController`/`BoardController` are protected by a route permission list containing board-view and contract-category permissions. Their task eager-load filters by `view_all_tasks` and assignee scope.
- **Route/nav gates:** `/tasks` route meta, `navigation/horizontal/tasks.ts`, `guards.ts` and the login landing logic all require board-view or service permissions.
- **API preflight:** `resources/ts/utils/apiAuthorization.ts` blocks requests client-side unless the current ability holds one of the mapped permissions (`POST /tasks` currently requires `create_tasks`).
- **Task editor:** `TaskDetailDrawer` unconditionally sends `dependencies` when saving; the backend then requires `manage_task_dependencies`. The dependency field must be hidden and omitted unless that permission is held.
- **Default grants:** `tests/TestCase.php` seeds the same catalog/grant map as production, so a change to `RolePermissionSeeder::grants()` covers the suite. Permission migrations use `insertOrIgnore` and intentionally keep grants on rollback.

### 4. Graph and impact review

- **Tools used:** direct source inspection, `code_search`, `glob`, and the checked-in `graphify-out/` manifests. No `graphify` skill is available in this session, and the artifacts are dated 2026-10-09, before later task/board changes, so they were used only as a dependency index. The actual call paths above were read in source.
- **Call paths traced:** `TaskController@store/update/destroy/move` → `TaskPolicy` → `TaskAccess`; `KanbanController`/`BoardController` → `Board::scopeVisibleTo`; `useTaskStore` → `TaskApi` → `apiAuthorization` → API; Kanban card/menu → drawer/dialog; auth guard/nav/login.
- **Downstream:** default grants affect every employee role and the test authorization catalog. Board visibility for finance/document officers expands to the Tasks area but only to their own personal board plus any service boards their contract permissions already allow; shared task rows remain permission-gated.
- **Limitations:** no runtime browser check of every role; focused staff and finance-role API tests plus one staff UI pass will be used.

### 5. Clarifications, decisions, and assumptions

- **Naming:** the request's “create-personal-tasks” maps to the repository's snake_case permission convention: `create_personal_tasks`.
- **“All employees”:** all operational roles in `RoleHierarchy::LEVELS` except `applicant` (not an employee) and `super_admin` (already bypasses). This includes viewer, document officer and finance officer.
- **“All CRUD actions”:** CRUD of the task record itself plus reordering within the same personal board. Comments, subtasks, attachments, tags and assignee management keep their dedicated permissions.
- **Read scope:** a holder may read all tasks on their own personal board, including tasks not assigned to them; this is required for the owner to manage their board and avoids a task disappearing when reassigned.
- **Editing dependencies:** remains gated by `manage_task_dependencies`; the editor will omit the field for holders without it so ordinary personal task edits still save.
- **No new board mutation surface:** boards stay product-owned; the permission grants task actions only.

### 6. Implementation plan

- [x] Permission catalog and defaults: add `create_personal_tasks` to `PermissionSeeder`, append it to every employee role in `RolePermissionSeeder`, and add `2026_11_07_000002_seed_create_personal_tasks_permission.php` for existing databases.
- [x] Backend policy: add personal-board helpers to `TaskAccess`; allow personal create/read/update/delete/move in `TaskPolicy`, restricted to the owner and the same personal board for moves.
- [x] Backend visibility/access: add a task visibility scope for the personal-owner exception and use it in `KanbanController`/`BoardController`; allow `create_personal_tasks` through the board/task read route middleware.
- [x] Frontend access: add the permission to the `/tasks` route, Tasks nav, landing redirect and API preflight map.
- [x] Frontend board behaviour: thread personal/owned flags into Kanban data; gate add, menu edit/delete and drag per board; add board-aware guards in `useTaskStore`.
- [x] Editors: make `KanbanBoardEditDrawer` and `TaskDetailDrawer` board-aware and hide/omit dependencies without `manage_task_dependencies`.
- [x] Tests: add `PersonalTaskPermissionTest` covering default grants, create/read/update/delete/move on the owner's board, denial on shared/foreign boards, no-permission denial, and board route access for a role without board grants.
- [x] Validation: run the focused PHP test, `npm run typecheck` (check changed files only), `git diff --check`, apply the migration, and do a focused Playwright staff check.

### 7. Validation plan and results

- [x] `php artisan test --filter=PersonalTaskPermissionTest` — **6 passed (51 assertions)**. Initial reruns hit deadlocks because another test process was migrating the shared `testing` database; after it finished, the class passed cleanly.
- [x] Adjacent task/board regressions — `PersonalBoardIsolationTest`, `RoleBasedAuthorizationTest`, `ApplicantTaskIntegrationTest`, `DepartmentTaskAssignmentTest`: **13 passed (70 assertions)**.
- [x] `npm run typecheck` — exits 2 with the same **74 pre-existing** errors; **none are in the changed files** (`authorization`, `useTaskStore`, Kanban, editors, router/nav, types).
- [x] `git diff --check` — exit 0.
- [x] Seed migration — `2026_11_07_000002_seed_create_personal_tasks_permission` applied locally; permission id 259; grants present for admin, case_officer, counselor, document_officer, finance_officer, manager, senior_counselor, staff, super_admin, team_lead and viewer.
- [x] Playwright staff check (`ahmad.karimi`, staff, no `create_tasks`) — only the Personal column shows “Add new task”; service columns do not. Created “Personal permission check”, the editor opened editable, saved “Personal permission check edited”, then used Task actions → Delete Task → confirmation and the card disappeared. Console: 0 errors, 1 Vue readonly-prop warning from the existing dialog prop handling.
- Checks not run: full PHPUnit suite and browser checks for every employee role; the API tests cover the permission-only role and the staff UI flow.

### 8. Risks and mitigations

- **Shared-board leakage from the personal exception** — every personal policy helper checks `isPersonal()` and owner id; the task visibility scope adds the personal-owner branch only alongside the existing assignee branch, and move requires the destination to be the same personal board.
- **Breaking the read-only staff behaviour** — the new permission adds actions only on the owner's Personal board; staff still lack create/edit/delete on shared boards.
- **Frontend-only gates diverging from the policy** — server checks remain authoritative; API preflight is updated to allow the new path, not to replace policy.
- **Dependency edits failing for personal-only users** — hide and omit dependencies without `manage_task_dependencies`.
- **Seed migration on existing data** — `insertOrIgnore` for the permission and role pivot, no destructive changes; rollback keeps custom grants.

### 9. Progress log

- 2026-11-07 — DISCOVERY: traced personal-board creation/isolation, task policies, controllers, frontend store/UI gates, route/nav/login gates, permission seeders and the role catalog. Confirmed checked-in graph artifacts are stale and used source inspection as the authority.
- 2026-11-07 — PLAN: finalized the approach above; implementation began.
- 2026-11-07 — IMPLEMENTATION: added the catalog entry, employee default grants and seed migration; added personal-board helpers and policy/visibility changes; opened the board/task routes and frontend gates; made the Kanban add/menu/drag and both editor drawers board-aware; hid/omitted dependencies without their grant; added `PersonalTaskPermissionTest`.
- 2026-11-07 — VALIDATION: 6 new tests (51 assertions) and 13 adjacent regressions (70 assertions) passed; typecheck had no changed-file errors; `git diff --check` clean; migration applied; staff Playwright flow passed create/edit/delete with only the Personal column exposing Add.
- 2026-11-07 — COMPLETED.

### 10. Completion summary

- Delivered: `create_personal_tasks` with employee-wide default grants, a seed migration for existing installs, owner-only task CRUD/reordering on the Personal board, personal-aware task visibility and board routes, and board-aware Kanban controls/editors.
- Acceptance criteria satisfied: all requirements are implemented and verified. Shared boards and another employee's personal board remain denied.
- Tests/checks passed: 6 new feature tests, 13 adjacent regression tests, migration verification, typecheck with no changed-file errors, clean diff check, and the staff Playwright create/edit/delete flow.
- Remaining limitations: comments, subtasks, attachments, tags and assignee management keep their existing dedicated permissions; dependency editing stays hidden without `manage_task_dependencies`. The full PHPUnit suite and a browser sweep of every employee role were not run.
