## Task: Kanban task cards — contract noise, avatar-only assignees, read-only view and card menu

- **Task ID:** task-kanban-readonly-view-and-card-actions
- **Created:** 2026-11-07
- **Last updated:** 2026-11-07
- **Status:** COMPLETED
- **Outcome:** Contract-generated detail is gone from task cards, assignees render as avatars only, clicking a card opens a polished read-only task view for every board reader, and a three-dot menu offers the permitted actions (view, edit, comments, attachments, delete) with confirmation.

### 1. Requirements and acceptance criteria

- [x] Contract-linked delivery tasks no longer display the generated contract number / fee / payment / start-date summary on the kanban card.
- [x] Existing contract-generated task descriptions are cleaned by a data migration; user-written descriptions are preserved.
- [x] Kanban cards show assignees as avatars only, with no comma-separated name text.
- [x] Clicking anywhere on a task card opens a read-only task view for any user who can see that board/task, including regular employees.
- [x] Each card has a three-dot menu with view, edit (when `edit_assigned_tasks`), comments (when `create_comments_on_assigned_tasks`), attachments, and delete (when `delete_assigned_tasks`).
- [x] Deleting from the menu requires confirmation.
- [x] The read-only view is responsive, works in RTL and LTR, and shows title, column, priority, dates, assignees, tags, description, read-only checklist and attachments.
- [x] New user-facing strings exist in en, fa-AF and ps-AF.
- [x] Focused PHP test passes; Playwright CLI verifies the cards and dialogs. Full `npm run typecheck` / `npm run i18n:check` still report only pre-existing unrelated failures.

### 2. Scope and non-goals

- **In scope:** `ContractTaskService` description generation, a data migration for legacy generated descriptions, `KanbanCard`, `KanbanItems`, `KanbanBoard`, `KanbanView`, a new read-only task dialog, locales, one focused PHP assertion, and Playwright verification.
- **Out of scope:** the Todo Matrix / Gantt / Calendar views, task API contracts, permission changes, board layout changes, drag-and-drop behaviour, and any contract document/print work.

### 3. Project findings

- **Contract fee source:** `app/Services/ContractTaskService.php::describe()` wrote `Contract <number> · Service fee: <fee> · Payment: <status> · Starts <date>` into the task description. Older rows use `Fee:`.
- **Card:** `resources/ts/pages/kanban/KanbanCard.vue` rendered the description and an `Assignees: names` line beneath the avatar group.
- **Card click:** `KanbanItems.vue` only opened the edit dialog when `edit_assigned_tasks` was held; users without it could not open a task at all.
- **Editors:** `KanbanView` opens `TaskDetailDrawer` (edit form) via the board's `editItem` event. `KanbanBoardEditDrawer` is only used for the just-created card through `openItemId`, so it stayed untouched.
- **Access:** the `/tasks` page is only reachable through board permissions; `KanbanController` returns only visible boards/tasks. A card that renders is therefore safe to view.
- **Staff permissions:** the `staff` role deliberately has `view_assigned_tasks` but not `edit_assigned_tasks` / `delete_assigned_tasks`; managers and above hold the edit grant. The menu therefore mirrors those gates, and staff see view/comments/attachments only.
- **Translations:** `components.taskManager.kanbanview` exists in all three locales; shared labels already exist for board, priority, dates, edit/delete, checklist and confirmations.

### 4. Graph and impact review

- **Tools used:** repository `code_search`, `glob`, targeted `read_files`, `git status`, and live Playwright snapshots of `/tasks`.
- **Callers traced:** `KanbanView → KanbanBoard → KanbanItems → KanbanCard`; `useTaskStore.updateTask/deleteTask`; `TaskController` and `TaskPolicy` for the permissions; `ContractTaskService` from `ContractGenerationService` and `ApplicantContractStageController`.
- **Downstream:** locale JSON validity and i18n checks, `npm run typecheck`, `ContractTaskAssignmentTest`, and the migration on the local dev database. No API shape changes, so API clients are unaffected.

### 5. Clarifications, decisions, and assumptions

- The user's reference to "contract fee" is the generated contract summary, not a user-authored description. The summary is removed at the source and legacy generated descriptions are cleared, so the detail cannot reappear in any task surface.
- Clicking a card always opens the read-only view; editing is reachable only through the card menu and remains permission-gated. This keeps the card click non-destructive for regular employees.
- The card menu also exposes the existing comments and attachments dialogs so actions are discoverable on small screens.
- Delete from the menu opens the existing delete-confirmation pattern rather than deleting immediately.
- No new permission is introduced: the menu uses the same gates as the existing board actions.
- The read-only description is rendered as plain text (tags stripped) rather than injecting stored HTML.

### 6. Implementation plan

- [x] Backend: stop generating contract financial detail in `ContractTaskService`; add a precise data migration that nulls only generated `Contract … Fee/Service fee …` descriptions on contract-linked tasks; add a focused assertion to `ContractTaskAssignmentTest`.
- [x] Card: remove the assignee-name line, add card click → `viewItem`, add a three-dot `VMenu` with permission-gated actions, keep the icon footer for comments/attachments.
- [x] Board plumbing: pass `viewItem`/`editItem`/`deleteItem` through `KanbanItems` and `KanbanBoard`; stop gating the card click on `edit_assigned_tasks`.
- [x] Kanban view: open the new read-only dialog from a card click, keep the edit drawer for the menu, and add a confirmation dialog for delete.
- [x] New `TaskViewDialog.vue`: polished read-only task details with meta grid, assignees, tags, description, checklist, attachments and download action.
- [x] Locales: add `taskActions` and `subtasksProgress` in en/fa-AF/ps-AF; remove the stray English `(Tags)` parenthetical from the Dari label.
- [x] Validate: `php artisan migrate`, focused PHP test, `npm run typecheck`, `npm run i18n:check`, and Playwright checks as admin and as a regular employee.

### 7. Validation plan and results

- [x] PHP migration + focused test — `php artisan migrate` applied `2026_11_07_000001_clear_generated_contract_task_descriptions` successfully and the generated-description count is 0; `php artisan test --filter=ContractTaskAssignmentTest` → **2 passed (8 assertions)**.
- [x] Frontend types — `npm run typecheck` exits 2 with **74 pre-existing errors** in untouched files (`applicant-case.vue`, `staff_list.vue`, `support.vue`, etc.); no error references any changed file (`KanbanCard`, `KanbanItems`, `KanbanBoard`, `KanbanView`, `TaskViewDialog`, `ContractTaskService`).
- [x] Locales — all three locale files parse; `npm run i18n:check` exits 1 only for pre-existing missing keys and does not report `taskActions` or `subtasksProgress`. Dari tags label now reads `برچسب‌ها`.
- [x] Browser — Playwright CLI:
  - Admin board: no contract fee/start-date summary on cards, no comma-separated assignee names, avatars only, three-dot button on every card.
  - Admin card click: polished read-only dialog with board/priority/due chips, meta grid, assignees, tags, task content, read-only checklist and Close.
  - Admin menu: View full details, Edit Task, Task Comments, Attachments, Delete Task; Edit Task opens the existing edit dialog; Delete Task opens "Confirm Deletion" and Cancel leaves the task intact.
  - Regular employee (`ahmad.karimi`): card click opens the same read-only dialog with no editable inputs; the menu shows View full details, Task Comments and Attachments only because the staff role has no parent-task edit/delete permission by design.
  - Mobile 390×844: dialog fits the viewport, scrolls its body and keeps the Close footer visible.
  - RTL (Dari): board columns, card footer and dialog mirror correctly, checklist progress runs right-to-left, and the dialog keeps its spacing and footer.
- [x] `git diff --check` exit 0.

### 8. Risks and mitigations

- **Clearing a real description** — the migration only targets contract-linked tasks whose description starts with the generated `Contract … · Fee:` / `Contract … · Service fee:` pattern.
- **Card click breaking drag** — the drag-and-drop container was untouched; menu and footer controls use `@click.stop`. A live drag gesture was not re-run as part of this verification.
- **Permission mismatch** — the menu mirrors the existing client-side gates; the server still enforces policy.
- **RTL clipping in the dialog** — responsive meta grid and logical spacing verified in Dari on desktop.

### 9. Progress log

- 2026-11-07 — DISCOVERY: traced the contract-generated description to `ContractTaskService::describe()`, confirmed the live card output, and mapped the card click path and permission gates.
- 2026-11-07 — PLAN: finalized the approach above before editing.
- 2026-11-07 — IMPLEMENTATION: removed the generated contract summary and added the cleanup migration; rewrote the card with avatar-only assignees, card-click viewing and the action menu; threaded view/edit/delete through the board; added `TaskViewDialog`; updated locales and the focused test.
- 2026-11-07 — VALIDATION: ran the migration and focused test, typecheck, i18n audit, and Playwright checks as admin and staff on desktop, mobile and RTL; confirmed delete confirmation and menu-gated editing.
- 2026-11-07 — COMPLETED.

### 10. Completion summary

- **Delivered:** contract-free cards, avatar-only assignees, a read-only task dialog opened from the card or its menu, a three-dot action menu with permission-gated view/edit/comments/attachments/delete, delete confirmation, a data cleanup migration, and translations.
- **Acceptance criteria satisfied:** all items above, except that dark mode was not separately screenshot-verified (the dialog uses Vuetify theme tokens rather than fixed colours).
- **Tests/checks passed:** focused PHP test (2 passed, 8 assertions), migration, locale JSON parsing, and the Playwright flows described above. `npm run typecheck` and `npm run i18n:check` still exit non-zero only because of pre-existing unrelated errors/missing keys.
- **Remaining limitations or follow-up work:** regular staff cannot edit or delete parent tasks by design (they can still view, comment and use attachments). A drag-and-drop gesture was not exercised during this verification. Dark mode should be spot-checked when next touching the dialog styles.
