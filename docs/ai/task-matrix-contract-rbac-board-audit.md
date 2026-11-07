## Task: Task Matrix links, personal-board task creation, contract-category RBAC, read-only boards, and audit-off defaults

- **Task ID:** task-matrix-contract-rbac-board-audit
- **Created:** 2026-11-05
- **Last updated:** 2026-11-05
- **Status:** COMPLETED
- **Outcome:** Visa-case tasks open in the Tasks → Todo Matrix for viewers allowed to see them; visa-case task creation is limited to the actor's personal board; contract access is separated by service/employment/other categories; boards are read-only and follow service-contract access; no audit resource is enabled by default.

### 1. Requirements and acceptance criteria

- [x] A task shown on a visa case links to Tasks → Todo Matrix and opens that exact task in the matrix, but only when the viewer is allowed to view the task.
- [x] Creating a task from a visa case offers/uses only the current user's personal board.
- [x] Separate RBAC exists for private, academy, test-offer, and scholarship service contracts, plus employment contracts and other contracts.
- [x] Board creation, rename, delete, reorder, and membership mutation are removed from the API and UI; only reading boards remains.
- [x] Access to a service board requires access to that service contract category; personal boards remain owner-only.
- [x] Newly registered audit resources and fresh installations default to auditing disabled; audit history is preserved.

### 2. Scope and non-goals

- **In scope:** the five requested workstreams end to end (backend policy/API, frontend contracts/UI, permissions/seeders/migrations, focused tests).
- **Out of scope:** unrelated working-tree changes, a new task-board architecture, restoring removed payment/invoice/commission entities, changing contract print layouts, websockets, or a general RBAC redesign.

### 3. Project findings

- **Task Matrix:** `resources/ts/components/task-manager/TaskAppShell.vue` switches between Kanban, Todo Matrix (`views/TodoMatrixView.vue`), and settings. The active tab is Pinia state (`useTaskStore.activeView`), not route state. The selected matrix task is `selectedTaskId`; no query-param deep link exists.
- **Deep-link loading gap:** only `KanbanView.vue` calls `store.fetchBoardData()` on mount. Opening `/tasks` directly on the Todo Matrix would otherwise have no boards/tasks. The shell or matrix must load data before selecting a task.
- **Case task payload:** `ApplicantController@show` loads the `tasks` relation directly (not through `TaskResource`), and `applicant-case.vue` renders those rows. There is no per-task `can_view` flag yet; adding one should use `TaskAccess::canViewTask`/the task policy so board/service access and assignment rules are respected.
- **Case task creation:** `applicant-case.vue` loads every visible board via `BoardApi.getAll()` and shows a board select. `TaskApi.create` posts `board_id`, title, priority, due date, and `applicant_id`. The personal board is already resolved lazily by `BoardController@index`/`KanbanController@index`, and `BoardResource` exposes `is_personal`/`is_owned`.
- **Board CRUD:** mutating endpoints are `POST /boards`, `PUT /boards/reorder`, `PUT /boards/{board}/members`, `PUT /boards/{board}`, and `DELETE /boards/{board}`. Controller methods, `BoardPolicy` write methods, `BoardApi`/`KanbanApi`, `useTaskStore.createBoard/updateBoard/deleteBoard/reorderBoards`, and Kanban UI buttons/drag handles all participate.
- **Board access:** `Board::scopeVisibleTo` and `TaskAccess::canViewBoard` implement private personal boards plus shared membership/`view_all_boards`. `ServiceBoard` already maps `private → Private Contracts`, `scholarship → Scholarship`, `test_offer → Test Offer`, `academy → Academy`, with `Applicant Cases` as the unserviced fallback.
- **Route permission semantics:** `EnsurePermission` accepts any one of its arguments (`collect(...)->contains(...)`), so comma-separated route permissions are OR.
- **Task route/nav:** `/tasks` currently requires `view_all_boards` or `view_assigned_boards`; the nav item requires `view_assigned_boards`. Frontend route guards support `meta.anyPermissions`, but navigation links only support `action`/`subject`, so any service-permission route access also needs nav gating support or a preserved umbrella grant.
- **Contracts:** all service pages/routes currently use the umbrella `view_contracts`; `ContractAccess` handles employee scope and `view_all_contracts`. `ContractController@index` filters by `service`, `audience=employees` (type slug `employment`), or `audience=other` (slug `other` or non-system type); generic create resolves the template’s type, and `POST/PUT /contracts/{service}` handle service contracts. `ContractGenerationService` also calls `ContractAccess::canManage`.
- **Auditing:** `AuditResourceSetting.enabled` already has a schema default of `false`, but the original notification/audit migration seeds every registry key with `enabled=true`, and `AuditRegistry::ensureSettings()` is insert-or-ignore with `enabled=true`. `Auditable::writeAudit` checks `AuditRegistry::enabled()`, so existing installations currently audit everything. `AuditManagementTest` assumes audits exist immediately after creating an applicant, so tests will need an explicit enable step if defaults become off.
- **Working tree:** many unrelated modifications/untracked files already exist (finance, PWA, contacts, etc.). They must be preserved.

### 4. Graph and impact review

- **Tools actually used:** repository `code_search`, `glob`, targeted `read_files`, migration/test inspection, and `graphify-out/` artifacts. No executable `graphify` skill is available in this session; the checked-in graph artifacts are dated 2026-10-09 and are stale for the current working tree, so findings were confirmed with direct source reads.
- **Impact areas:** contract list/show/create/update/cancel/delete and service routes; contract nav/route guards; board read policies and task visibility; task creation and case serialization; task matrix deep link; audit registry/settings and every auditable model; role/permission seeders and existing role grants; tests for contracts, boards, tasks, audits, and personal-board isolation.
- **Risk hotspots:** `ContractAccess` is used by contract generation and all contract mutations; board visibility underpins task/attachment/comment authorization; removing board-write permissions can break stale frontend calls or tests; turning audits off by default changes audit-dependent tests and real deployments.

### 5. Clarifications, decisions, and assumptions

**User decisions:**

1. **Contract RBAC depth:** category access permission plus the existing action grants. Each of the six category permissions is required for any access to that category, including create/update/cancel/delete; `create_contracts`, `edit_contracts`, `cancel_contracts`, and `delete_contracts` remain the action gates.
2. **Audit rollout:** disable every existing `audit_resource_settings` row now via migration, in addition to changing the defaults. Existing audit history is retained.
3. **Default role grants:** all regular employee roles receive the five non-employment categories (private, scholarship, test offer, academy, other). Employment-contract access is limited to supervisors/admin (admin, manager, team lead).

**Assumptions already supportable from the code:**

- The "Task Matrix" is the existing Todo Matrix tab, reached with `/tasks?view=todos&task=<id>`.
- Service permission alone may grant the corresponding service board; personal boards remain owner-only. The non-service `Applicant Cases` fallback keeps the existing membership/`view_all_boards` rule.
- Removing board CRUD means removing write routes, controller/policy write methods, frontend write controls, board-write API helpers, and the now-unused board-write permissions.
- Audit history is never deleted; only future auditing/defaults change.
- Existing unrelated working-tree changes are preserved.

### 6. Implementation plan

- [x] Add `view_{private,scholarship,test_offer,academy,employment,other}_contracts`; resolve a contract/template to one category; enforce in `ContractAccess`, `ContractController` (list/show/preview/create/update/cancel/delete/service options), and `ContractGenerationService`.
- [x] Grant the five non-employment permissions to every role that currently has `view_contracts`; grant employment to admin, manager, and team lead; mirror in seeders and a migration.
- [x] Gate the six contract routes and in-page contract links with `view_contracts` plus the category permission; add `allPermissions` route/nav support where needed.
- [x] Remove board write routes, controller/policy methods, write permissions, API helpers, store actions, and Kanban create/rename/delete/reorder controls; keep board reads and lazy personal-board creation.
- [x] Make service boards visible when the matching service-contract permission is held (personal boards stay owner-only); include the service permissions in board/task route access.
- [x] Load Task Matrix data on direct entry, consume `?view=todos&task=<id>`, select the task, add per-task `can_view`, and link case tasks only when allowed.
- [x] Make visa-case task creation select the caller's personal board and reject case-linked manual tasks on shared boards.
- [x] Change audit defaults to disabled and add a migration that disables all existing audit resource settings without deleting history.
- [x] Update focused tests and run the relevant PHP suites and `npm run typecheck`; browser checks for the two frontend flows were not run because no authenticated app session was available.

### 7. Validation plan and results

- Implemented and covered: contract-category filtering and cross-category denial, board read-only/service-access behavior, personal-board case-task creation, audit-disabled defaults, role grants, the Task Matrix data-loading/query path, and the per-task `can_view` payload.
- PHP: 19 affected suites across contracts, boards, tasks, permissions, notifications, payroll/off-days, and audits. The final sweep had 102 passing and one remaining compliance test failure; after correcting its invalid academy admission field, `VisaFirmComplianceTest` passed 3/3 (406 assertions). All affected suites are now green.
- Frontend: `npm run typecheck` exit 0; locale JSON parses for en/fa/ps; `git diff --check` clean.
- Browser: the case → Task Matrix link and personal-board dialog were not exercised in a live browser because no authenticated app session was available. Backend behavior is covered by tests, and the UI changes compile.

### 8. Risks and mitigations

- **Breaking existing contract access** — preserve current role grants by default once confirmed; add a migration rather than relying on reseeding.
- **Board access leaking or blocking work** — centralize the service→permission mapping in one support class/`BuiltInService` helper and cover both `scopeVisibleTo` and `canViewBoard`.
- **Removing board writes breaks stale calls** — remove routes and API helpers together, update/remove tests that assert board mutation, and leave personal-board lazy creation intact.
- **Audit-dependent tests and production expectations** — make the default change explicit in tests, keep history, and only reset existing settings if the user confirms.
- **Task Matrix empty on direct deep link** — load board data in the shell/matrix before selecting the target task; hide the case link when the server says `can_view=false`.

### 9. Progress log

- 2026-11-05 — DISCOVERY: mapped task matrix/store/case task payload, personal board resolution, board CRUD surfaces, service-board titles, contract route/access flow, permission middleware, audit registry/defaults, and relevant tests. Confirmed the checked-in graphify artifacts are stale; used direct source inspection as the authoritative fallback.
- 2026-11-05 — CLARIFICATION: user chose category access + existing action grants, immediate disable of all existing audit settings, and five categories for regular employees with employment limited to supervisors/admin.
- 2026-11-05 — PLAN: finalized the ordered implementation plan above; implementation started.
- 2026-11-05 — IMPLEMENTED: added six contract-category permissions and category-aware contract/API enforcement; granted five categories to employee roles and employment to admin/manager/team lead; removed board write API/UI/permissions and tied service boards to service-category access; added Task Matrix query loading, per-case-task `can_view`, the case link, and personal-board-only case task creation; disabled all audit resources by migration and changed defaults to off.
- 2026-11-05 — VERIFIED: all affected PHP suites pass, `npm run typecheck` passes, locale JSON is valid, and whitespace checks are clean. Browser verification remains optional because no authenticated session was available.

### 10. Completion summary

- Delivered: all five requested workstreams end to end: Task Matrix linking, personal-board case tasks, six-category contract RBAC, read-only/service-gated boards, and audit-off defaults with a data migration.
- Acceptance criteria satisfied: all six criteria are implemented; employment contracts are limited to admin, manager, and team lead, while the five non-employment categories are granted to employee roles (including the read-only viewer role).
- Tests/checks passed: 19 affected PHP suites, `npm run typecheck` (exit 0), locale JSON validation, and `git diff --check`.
- Remaining: optional live browser verification of the two frontend flows in an authenticated session; no implementation blockers.
