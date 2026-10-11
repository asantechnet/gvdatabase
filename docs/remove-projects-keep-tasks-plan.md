# Remove Projects, Keep Only Tasks — Plan & Progress

Goal: remove the **project** concept from the system entirely. Tasks (Kanban,
Calendar, Gantt, Todo matrix, Task settings) remain as a first-class feature,
but they are no longer scoped to, associated with, or validated against a
project. The global navigation/route rename from "Projects" to "Tasks" is part
of the same change.

Decisions confirmed with the user:

1. **Full removal (destructive).** Drop `projects` and `project_service`
   tables, drop `boards.project_id` and `expenses.project_id`, delete the
   `Project` model/controller/resource/routes/permissions and every
   project-only branch of code. Existing project rows are intentionally lost.
2. **Tasks page cleanup.** Remove the *Project portfolio* tab, the project
   workspace `VSelect`, and the *New Project* button/dialog from the tasks
   page. What remains: Kanban, Calendar, Gantt, Todo matrix, Task settings.
3. **Remove project links everywhere.** Expenses and recurring events lose
   their `project_id` field (UI, API types, validation, resource payloads).
4. **Routing.** `/tasks` becomes the canonical route named `tasks`. `/projects`
   and `/notes` redirect to `/tasks`. Navigation label is "Tasks" in all three
   languages.
5. **Task services.** The project-based service restriction is removed. Tasks
   may attach any active catalogue service with no customer/project validation.

---

## 1. Current architecture (recon)

- `/projects` route (`resources/ts/plugins/1.router/routes.ts`) renders
  `pages/tasks.vue` → `components/task-manager/TaskAppShell.vue`.
- `TaskAppShell.vue` imports `ProjectApi`, renders a **portfolio** tab
  (`views/ProjectPortfolioView.vue`), a project workspace selector, and a
  New Project dialog. This is the "projects subpage" to remove.
- `stores/useTaskStore.ts` holds `selectedProjectId`, default active view
  `'portfolio'`, and passes `project_id` to Kanban/Boards/reorder/create.
- Backend: `Project` model, `ProjectController`, `ProjectResource`,
  `Route::apiResource('projects', ...)`, 4 permissions
  (`view_projects`, `create_projects`, `edit_projects`, `delete_projects`).
- Schema (`0001_11_01_000011_create_kanban_tables.php`): `projects`,
  `project_service`, `boards.project_id` + index.
  (`0001_09_01_000009_create_finance_tables.php`): `expenses.project_id` + index.
  (`0001_15_01_000015_add_cross_section_foreign_keys.php`): `expenses.project_id`
  foreign key.
- `TaskController` uses `board->project` for default applicant, applicant
  matching, service matching, cross-project move and cross-project dependency
  guards.
- `BoardController` / `KanbanController` filter and validate `project_id`.
- `ExpenseController` / `ExpenseResource` expose `project_id` + `project`.
- `RecurringEventController` validates `payload.project_id`;
  `RecurringEventService::createTask` reads `board->project`.
- `ApplicantTaskService` creates its board with `project_id => null`.
- `Applicant`, `Service`, `Expense`, `Board` models carry project relations.
- Tests: `ProjectServiceAssignmentTest.php`, `BusinessLogicConsistencyTest.php`,
  `ApplicantTaskIntegrationTest.php`.

---

## 2. Backend plan

### 2.1 Schema
- **Edit** `0001_11_01_000011_create_kanban_tables.php`: remove `projects`,
  `project_service`, `boards.project_id` + `boards_project_id_order_index`.
- **Edit** `0001_09_01_000009_create_finance_tables.php`: remove
  `expenses.project_id` + `expense_project_idx`.
- **Edit** `0001_15_01_000015_add_cross_section_foreign_keys.php`: remove the
  `expenses.project_id` foreign key.
- **Add** `2026_10_06_000001_drop_projects_tables_and_columns.php`: guarded
  (`hasTable` / `hasColumn`) drop of the same objects, so existing databases
  migrate safely. Fresh databases are already clean from the edited originals.

### 2.2 Remove Project artefacts
- Delete `app/Models/Project.php`, `app/Http/Controllers/ProjectController.php`,
  `app/Http/Resources/ProjectResource.php`.
- `routes/api.php`: delete the `projects` apiResource block.
- `Applicant`: drop `projects()` relation.
- `Service`: drop `projects()` relation.
- `Expense`: drop `project()` relation, `project_id` from `$fillable` and casts.
- `Board`: drop `project()` relation and `project_id` from `$fillable`.
- `ServiceController`: drop `$service->projects()->exists()` from the delete
  guard.
- `ApplicantResource`: drop the `projects` payload.
- `AuditRegistry`: no project registration exists (verified) — no change.

### 2.3 Controllers / services
- `BoardController`: remove `project_id` filter, validation and order scope.
- `KanbanController`: remove `project_id` filter.
- `BoardResource`: remove `projectId`.
- `TaskController`: remove `assertApplicantMatchesProject`,
  `assertServicesMatchProject`, the move cross-project guard, the dependency
  cross-project guard, and the `board->project` default applicant. Task
  `applicant_id` and `service_ids` stay as plain optional fields.
- `ExpenseController`: remove `project_id` validation, eager loads and filter.
- `ExpenseResource`: remove `project_id` + `project`.
- `RecurringEventController`: remove the `payload.project_id` rule.
- `RecurringEventService::createTask`: stop reading `board->project`.
- `ApplicantTaskService`: drop the `project_id => null` key and the
  project-board comment.

### 2.4 Permissions & seeders
- `PermissionSeeder`: remove the four project permissions.
- `RolePermissionSeeder`: remove them from the granted list.
- Delete `tests/Feature/ProjectServiceAssignmentTest.php`; update
  `BusinessLogicConsistencyTest.php` and `ApplicantTaskIntegrationTest.php` to
  drop project setup/assertions.

---

## 3. Frontend plan

### 3.1 Remove Project artefacts
- Delete `resources/ts/api/project.ts` and
  `resources/ts/components/task-manager/views/ProjectPortfolioView.vue`.

### 3.2 Tasks page
- `TaskAppShell.vue`: remove ProjectApi/ApplicantApi imports, projects state,
  the portfolio tab, the workspace selector, and the New Project dialog.
- `stores/useTaskStore.ts`: remove `selectedProjectId` + `selectProject`,
  remove `'portfolio'` from the view union, default view → `kanban`, drop
  `project_id` params from Kanban/Board/reorder/create calls.
- `TaskDetailDrawer.vue`: drop `ProjectApi`; load the full active service list
  via `ServiceApi` since the project restriction is gone.
- `KanbanView.vue` / `GanttView.vue`: rename the project-flavoured headings to
  task headings.

### 3.3 API contracts
- `api/kanban.ts`, `api/board.ts`: remove `projectId`, `project_id`, and the
  `project_id` params.
- `api/expenses.ts`: remove `project_id` + `project` from types and params.

### 3.4 Expenses & recurring events
- `pages/expenses.vue`: remove ProjectApi, project state/options, the project
  column and the `project_id` payload fields.
- `components/forms/ExpenseFormDialog.vue`: remove `projectOptions` prop/field.
- `pages/recurring-events.vue`: remove ProjectApi, project options and the
  `payload.project_id` field.

### 3.5 Routing & navigation
- `routes.ts`: `{ path: '/tasks', component: 'tasks', name: 'tasks', ... }`;
  `{ path: '/projects', redirect: { name: 'tasks' } }`;
  `{ path: '/notes', redirect: { name: 'tasks' } }`.
- `guards.ts`: landing route `{ name: 'tasks' }`.
- `navigation/horizontal/tasks.ts`: `title: 'common.navigation.tasks'`,
  `to: 'tasks'`.
- `layouts/components/NavSearchBar.vue`: target route `tasks`.
- `pages/home.vue`, `pages/login.vue`: `/projects` → `/tasks`.
- `utils/apiAuthorization.ts`: remove the `projects` resource entry.

### 3.6 Misc
- Rename `components/ProjectFormWarningGuard.vue` → `FormWarningGuard.vue` and
  the `project-form-pristine` event → `app-form-pristine` (App.vue +
  `pages/contract/policies.vue`), since the name is a projects-era leftover.

### 3.7 i18n
- `common.navigation.tasks` becomes the Tasks label ("Tasks" /
  "وظایف" / "دندې") and is used by navigation + search.
- Remove projects-only keys: `components.taskManager.projectPortfolio.*`,
  project keys in `taskappshell.*`, `projectBoard`, `projectGanttTimeline`,
  `projectServiceHint`, `navsearchbar.projects`, and the `common.navigation.
  projects` entry. Rename surviving headings (`taskBoard`, `ganttTimeline`).
- Apply to `en`, `fa-AF`, `ps-AF`.

---

## 4. Verification

- `php -l` on every new/changed PHP file.
- `node -e "JSON.parse(...)"` for the three locale files.
- `npm run typecheck` — must not add errors beyond the pre-existing baseline.
- Note: `vite build` cannot run in this sandbox (Windows-installed
  `node_modules` / native binary mismatch), so bundle verification is deferred
  to the dev machine.

---

## 5. Progress

- [x] 0. Recon + user decisions
- [x] 1. Plan written
- [x] 2. Backend schema (edits + drop migration)
- [x] 3. Backend model/controller/resource/route/permission removal
- [x] 4. Backend tests updated
- [x] 5. Frontend task page + store + API cleanup
- [x] 6. Frontend expenses/recurring events cleanup
- [x] 7. Frontend routing/navigation/guards rename
- [x] 8. i18n rename + project key cleanup
- [x] 9. Verification + progress update

### Change log

- **Schema**: edited `0001_11_01_000011_create_kanban_tables.php`,
  `0001_09_01_000009_create_finance_tables.php`,
  `0001_15_01_000015_add_cross_section_foreign_keys.php`; added
  `2026_10_06_000001_drop_projects_tables_and_columns.php`.
- **Backend removed**: `Project`, `ProjectController`, `ProjectResource`;
  project routes/permissions/relations/guards and every `project_id` branch in
  boards, kanban, tasks, expenses, recurring events and the applicant task
  service.
- **Frontend removed**: `api/project.ts`, `ProjectPortfolioView.vue`, the
  project portfolio tab / workspace selector / New Project dialog, project
  state in the task store, and `project_id` across the API contracts, expenses
  and recurring-events pages.
- **Rename**: route/nav/search now use `tasks`; `/projects` and `/notes`
  redirect to `/tasks`; `ProjectFormWarningGuard` renamed `FormWarningGuard`
  with an `app-form-pristine` event.

### Verification run here

- `php -l` — clean on all 24 changed/new PHP files.
- `JSON.parse` — en / fa-AF / ps-AF all parse.
- `npm run typecheck` — 20 remaining errors, all pre-existing (ContractForm,
  InvoiceSelect, announcements, applicant-case, applicant-stages,
  contract/policies, educational-master-data, home, innvoice, notifications).
  **No errors in any touched file.** The two project-related errors that used
  to exist in `expenses.vue` and `recurring-events.vue` are gone.
- `npm run i18n:check` — passes; no new missing-key entries for the renamed
  keys (ps-AF keeps its pre-existing `taskappmanager`/gantt backlog, which
  falls back to English).
- `vite build` could not run in this sandbox (Windows-installed `node_modules`
  native-binary mismatch), so bundle verification is deferred.

### Notes / deviations

- The edited kanban migration replaces the `boards.project_id` index with a
  plain `boards_order_index` so board ordering stays indexed.
- `data-project-warning-dialog` in `ActionWarningDialog.vue` was renamed
  to `data-form-warning-dialog` to match the renamed guard.
- Vuexy demo leftovers that merely contain the word "project"
  (`ShareProjectDialog.vue`, `CreateAppDialog`, `UserInfoEditDialog.projectDone`,
  the `shareprojectdialog` locale block, the `projectCompletion`/`projectMilestone`
  event-type labels) are unrelated to the delivery-project feature and were left
  untouched.

### Still to run on the dev machine

- `npm run build` (the tracked `public/build/*` assets must be regenerated;
  the current bundle still contains the old `project-*.js` chunk).
- Apply the migration to any existing database
  (`php artisan migrate`).
- Manual pass: `/tasks` loads all five tabs, `/projects` + `/notes` redirect,
  expenses and recurring events save without a project, task detail drawer
  lists active services, and print layouts are unaffected.
