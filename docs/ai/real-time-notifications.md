# Task record: Real-time notification system

- **Task ID:** real-time-notifications
- **Created:** 2026-10-13
- **Last updated:** 2026-10-13
- **Status:** PLANNED (planning only — no code changed)
- **Outcome:** Add a heartbeat-driven, flag-gated real-time notification path on top of the existing notification tables. A per-user `users.unread_notifications` flag turns on when a notification is delivered; a heartbeat endpoint returns the user's unread notifications only while the flag is set, then clears it; the frontend polls every 30s, plays `notification-ding.mp3` once per real-time batch and once on app launch when unread notifications already exist, and clicking a notification navigates to its section. Task-assignment and payment-request notifications already exist and will flow through this channel; the one new notification is telling the admission employee when a contract line is created. Documented in `AGENTS.md`.

## 1. Requirements and acceptance criteria

- [ ] AC1 `users.unread_notifications` boolean column exists (`default false`); `User` casts it to boolean.
- [ ] AC2 When a notification is delivered to a user, that user's `unread_notifications` flag is set true in the same delivery operation.
- [ ] AC3 `GET /api/notifications/heartbeat` returns the user's unread notifications **only** when the flag is true; when false it returns an empty payload and does not query notification content.
- [ ] AC4 After returning the queued unread notifications, the heartbeat clears the flag for that user (atomically, no double-send under concurrent requests).
- [ ] AC5 The heartbeat channel is independent of the existing on-launch inbox retrieval (`GET /notifications`); the existing load, 60s poll, mark-read, delete and UI are unchanged.
- [ ] AC6 The frontend polls the heartbeat every 30s while the app is open (only when the user holds `view_own_notifications`).
- [ ] AC7 `notification-ding.mp3` plays once per real-time heartbeat batch that contains new notifications; on app launch it plays once when the user already has unread notifications, never once per notification.
- [ ] AC8 Clicking a notification navigates to its `go_to_url` section (existing behaviour, preserved for heartbeat-delivered items).
- [ ] AC9 Task creation assigns notify all task assignees (already implemented via `taskAssigned`; verify every assignment path, add regression test).
- [ ] AC10 Creating a payment request notifies the finance audience (already implemented via `pendingSubmitted`; verify + regression test). Payment-request audience remains the approvers as today (see §5 decision).
- [ ] AC11 Creating a contract line notifies the applicant's **admission employee** (new).
- [ ] AC12 Delivery targets are strictly permission/role-scoped; a user without the required permission receives nothing. Regression test asserts this.
- [ ] AC13 Query indexes support the heartbeat/unread path.
- [ ] AC14 The system is documented in `AGENTS.md`.

## 2. Scope and non-goals

- **In scope:** `users.unread_notifications` migration + indexes; flag set in `Notification::deliverTo()`; heartbeat endpoint + route + API contract + auth mirror; frontend 30s poller + audio + inbox merge; admission-employee recipient in `contractGenerated`; `AGENTS.md` section; feature tests.
- **Out of scope:** a websocket/broadcast stack (none exists, and the user chose heartbeat polling); changing the existing inbox UI/behaviour; replacing the notification preference system; email/push delivery; broadening the payment-request audience (explicitly deferred — see §5); changing who can mark notifications read.

## 3. Project findings

### Existing notification stack (reused)

- **Tables** (`database/migrations/0001_14_01_000014_create_notification_and_audit_tables.php`):
  - `notifications` — `id, type(50), notification_key(100), title, message, data(json), date_time, employee_id, go_to_url(2048), timestamps`; indexes `(employee_id, date_time)`, `(notification_key, date_time)`.
  - `notification_user` — composite PK `(notification_id, user_id)`, `is_read(nullable)`, `read_at`, `deleted_at`, timestamps; unique `(notification_id, user_id)`; index `(user_id, deleted_at, is_read)`; FKs cascade on delete.
- **Models:** `app/Models/Notification.php` (`deliverTo(iterable $userIds)`, preference filter via `account_settings.notifications.<key>.in_app`, `preferenceKey()`), `app/Models/NotificationUser.php` (pivot casts).
- **Controller/Resource/routes:** `app/Http/Controllers/NotificationController.php` (index/show/store/update/markAllRead/destroy); `app/Http/Resources/NotificationResource.php` (exposes `is_read` from the loaded pivot); routes in `routes/api.php:480-485` — `PATCH notifications/mark-all-read`, then `apiResource('notifications')` with per-action permissions (`view_own_notifications`, `send_notifications`, `update_own_notifications`, `delete_own_notifications`).
- **Delivery choke point:** `Notification::deliverTo()` is called by `EducationalWorkflowNotificationService` (all its `notifyOnce` methods), `ContractExpiryNotificationService`, `ContractTemplateVersionNotificationService`, `app/Models/Concerns/Auditable.php`, and `NotificationController::store`. It already filters recipients by notification preference before syncing the pivot — so it is the single correct place to set the flag.
- **Frontend:** `resources/ts/api/notification.ts` (typed, unwraps `response.data.data`); `resources/ts/layouts/components/NavBarNotifications.vue` — loads on mount and polls `getNotifications` every 60s, supports mark read/unread and delete; `resources/ts/@core/components/Notifications.vue` (renders `isSeen`/`goToUrl`); `resources/ts/pages/notifications.vue`; `resources/ts/utils/notificationText.ts`. `NavBarNotifications` is mounted once by each of `DefaultLayoutWithVerticalNav.vue`, `DefaultLayoutWithHorizontalNav.vue`, and `ApplicantPortalLayout.vue`.
- **Authorization:** frontend `canAccess()` reads CASL abilities from permissions; `resources/ts/utils/apiAuthorization.ts` mirrors backend route permissions.

### Real-time transport

- **No websocket/broadcast dependency exists** (no reverb/pusher/echo/soketi in `composer.json`/`package.json`). Real time is implemented as short-interval HTTP polling (heartbeat), matching the user's wording and the 30s interval decision.

### Notification producers relevant to this task

- **Task assignment — already implemented.** `TaskController::store`/`update` call `EducationalWorkflowNotificationService::taskAssigned($task, $assignees, $actorId)`, which creates one idempotent notification per `(task, user)` (key `task.assigned.{task}.{user}`), skips the actor, and `deliverTo`s each assignee. `ContractTaskService::ensureForApplicantContract`, `ApplicantTaskService::ensureForStage`, and `RecurringEventService` also call it. So "notify an employee each time a task is assigned" is already satisfied; the new channel makes it instant.
- **Payment request — already implemented.** `PendingTransactionController::store` calls `pendingSubmitted()`, which notifies active users holding **`approve_pending_transactions`** (excluding the requester) and links to `/pending-transactions`. Payment requests are the `PendingTransaction` flow (`routes/api.php:~399`, `payment-requests` canonical with `loans`/`pending-transactions` aliases).
- **Contract generated.** `ContractGenerationService::create` creates the `Contract` and its `ApplicantContract` line, then calls `applicantAssigned` (primary only) and `contractGenerated`. `contractGenerated` currently notifies `applicant.responsibleEmployee` + `soldByEmployee` (key `contract.generated.{contract}`), **not** the applicant's `admissionEmployee`.

### Audio asset

- `notification-ding.mp3` is at the **repo root** (51 KB) and is not web-served. Vite serves `public/` (currently `build, favicon.svg, hot, index.php, loader.css, robots.txt, storage`), so the file must be copied to `public/sounds/notification-ding.mp3` (or imported) to be playable.

### Conventions

- TypeScript check: `npm run typecheck` (never bare `vue-tsc`/`tsc`). Icons: `npm run build:icons` (no new icon tokens are expected). Tests: `php artisan test` (SQLite in-memory, 379 passing on the current tree). Project instructions live in `AGENTS.md`.

## 4. Graph and impact review

- **Methods used:** repository search (`code_search`), `glob`, directory listing, and direct inspection of the notification stack, task controller/service, payment-request controller/routes, contract generation service, permission/role seeders, router/plugins, layouts, and build config. `graphify-out/` exists but was generated earlier (2026-10-09) and was **not** re-run for this task; findings come from source inspection. Treat this as a documented fallback, not a verified graph run.
- **Relationships identified:** `deliverTo()` is the single delivery choke point → flag set there; `TaskController`/`ContractTaskService`/`ApplicantTaskService`/`RecurringEventService` → `taskAssigned`; `PendingTransactionController::store` → `pendingSubmitted`; `ContractGenerationService::create` → `contractGenerated`; `NavBarNotifications` → `getNotifications` (60s) and the new heartbeat.
- **Downstream effects:** `users` gains a column; `notification_user` gains high-frequency flag toggling; a new route + API contract + auth mirror; the frontend gains one composable and one public asset; `AGENTS.md` gains a section.
- **Likely tests to touch/extend:** `tests/Feature/VisaFirmComplianceTest.php` asserts `contractGenerated` behaviour (line ~95) — the admission-employee addition must not break it.

## 5. Clarifications, decisions, and assumptions

### User decisions

1. **"Admission added to a contract"** → when a **contract line is created for an applicant**, notify the applicant's **admission employee**.
2. **Payment-request audience** → the user asked how the existing system works and decided: because it is already viable, **no new payment-request notification is needed**. The existing `pendingSubmitted` notification will flow through the new real-time channel automatically (since `deliverTo` sets the flag).
3. **Task assignment** → already exists; make sure an employee is notified each time a task is assigned to them (already true via `taskAssigned`; the new channel delivers it in real time).
4. **Heartbeat interval** → **30 seconds**.

### Deferred (flagged, not in this task)

- The existing payment-request notification goes to `approve_pending_transactions` holders, whereas the original request described `view_pending_transactions` holders ("subject to change"). Broadening/replacing that audience is **not** part of this task (decided above). If wanted later, it is a one-line change to `pendingSubmitted()` plus a test.

### Assumptions (low risk, recorded)

- The heartbeat reuses the existing `notifications` / `notification_user` tables so real-time items appear in the same inbox; only the retrieval channel is separate.
- `unread_notifications` is a new `users` boolean column (explicitly requested), not a reused field.
- "App launch ding" checks the existing unread inbox once and plays at most once per app session.
- Audio is served from `public/sounds/notification-ding.mp3` (a copy of the root file), because the repo root is not web-served.
- Notifications pushed by the heartbeat are merged into the existing inbox list (prepended) rather than replacing it, so the heartbeat does not interfere with the 60s inbox refresh.
- `contractGenerated` uses one shared notification row per contract and delivers to the recipient set at creation time. The admission employee is known at contract-generation time (set on the applicant), so including them in that set is sufficient for new/extension contracts. Pre-existing contracts (generated before the change) are not back-notified.

## 6. Implementation plan

### Backend

- [ ] **Step 1 — Migration** `database/migrations/2026_10_13_000004_add_unread_notifications_flag_to_users.php`:
  - `Schema::table('users', fn (Blueprint $t) => $t->boolean('unread_notifications')->default(false)->after('acc_status'));`
  - Indexes for the unread/heartbeat path (added on existing tables):
    - `notification_user`: `index(['user_id', 'is_read', 'deleted_at', 'notification_id'], 'notification_user_user_id_is_read_deleted_at_notification_id_index')` — covers "this user's undelivered/undrained rows" and the join back to notifications.
    - `notifications`: `index(['date_time', 'id'], 'notifications_date_time_id_index')` — supports the `orderByDesc('date_time')` listing once candidates are joined.
  - `down()` drops the indexes then the column.
- [ ] **Step 2 — `app/Models/User.php`:** add `'unread_notifications' => 'boolean'` to `$casts` (and to `$fillable` if the codebase fills it by mass assignment; otherwise leave it out and set via query builder). Add two small helpers used by delivery and heartbeat: `markNotificationsPending(): void` and `consumeNotificationsPending(): bool` (the latter returns the previous flag), or keep the logic inline — final choice at implementation time; helpers keep the transaction in one place.
- [ ] **Step 3 — `app/Models/Notification.php`:** in `deliverTo()`, after the successful `syncWithoutDetaching`, set `users.unread_notifications = true` for exactly the delivered recipient ids (`User::whereIn('id', $recipientIds)->update(['unread_notifications' => true])`). This runs inside the same operation as delivery. Do not set it when `$recipientIds` is empty. This is the only producer change needed — every existing notification flows through it.
- [ ] **Step 4 — Heartbeat endpoint** `NotificationController::heartbeat(Request $request): JsonResponse`:
  - Load the authenticated user. If `unread_notifications` is false → return `['success' => true, 'data' => []]` **without** querying notifications.
  - If true → in a `DB::transaction`: `User::whereKey($user->id)->lockForUpdate()` (serializes concurrent polls), re-check the flag, read the user's unread, non-deleted notifications joined/ordered by `date_time desc` (limit ~20), set the flag false, then return `NotificationResource::collection($rows)` (double `data` wrapper preserved).
  - Loading pivot state via the existing `visibleTo()`/`with('recipients' ...)` pattern so `NotificationResource` returns the recipient's `is_read`.
- [ ] **Step 5 — Route** in `routes/api.php` next to `notifications/mark-all-read` (line ~480, **before** `apiResource('notifications')` so `{notification}` does not swallow `heartbeat`):
  `Route::get('notifications/heartbeat', [NotificationController::class, 'heartbeat'])->middleware('permission:view_own_notifications');`
- [ ] **Step 6 — Admission employee recipient** in `EducationalWorkflowNotificationService::contractGenerated()`: `loadMissing('applicant.admissionEmployee')` and add `$contract->applicant?->admissionEmployee?->user_id` to the recipient collection (still filtered against the actor). Preserve the existing key and the responsible-employee/seller recipients.

### Frontend

- [ ] **Step 7 — Audio asset:** copy `notification-ding.mp3` → `public/sounds/notification-ding.mp3`.
- [ ] **Step 8 — API contract** `resources/ts/api/notification.ts`: add
  `export const fetchNotificationHeartbeat = async (): Promise<AppNotification[]> => { const response = await api.get('/notifications/heartbeat'); return (response.data.data ?? []) as AppNotification[] }`
  and add it to the default export. Keep the same `request()`/error conventions.
- [ ] **Step 9 — Heartbeat composable** `resources/ts/composables/useNotificationHeartbeat.ts` (composables are auto-imported):
  - Guard on `canAccess('view_own_notifications')` and on being authenticated.
  - Poll `fetchNotificationHeartbeat` every **30s**; `setInterval` cleared in `onUnmounted`.
  - When the response is non-empty: play the ding **once** for the batch, and emit/prepend the new notifications into the inbox (expose a callback or shared ref the navbar consumes).
  - Audio: a single reused `new Audio('/sounds/notification-ding.mp3')`; `.play().catch(() => {})` to tolerate browser autoplay policies; never play per item.
  - Include a one-time launch helper: `playLaunchDingOnce(hasUnread: boolean)` guarded by a module-level flag so it fires at most once per session.
- [ ] **Step 10 — `resources/ts/layouts/components/NavBarNotifications.vue`:** start the heartbeat poller on mount (so it mounts with every layout that already includes the navbar), merge heartbeat-delivered notifications into `rawNotifications` (prepend, de-duped by id) so they appear in the list and are clickable via the existing `go_to_url` handler, and call `playLaunchDingOnce(rawNotifications.value.some(n => n.is_read !== true))` once after the initial `loadNotifications()` resolves. Leave the existing 60s poll and all mark-read/delete behaviour untouched.
- [ ] **Step 11 — Auth mirror** `resources/ts/utils/apiAuthorization.ts`: add `'GET /notifications/heartbeat': ['view_own_notifications']` (place it before the generic `/notifications` GET rule so it matches exactly).

### Documentation & tests

- [ ] **Step 12 — `AGENTS.md`:** add a "Real-time notifications" subsection documenting: the `unread_notifications` flag semantics, delivery sets the flag, heartbeat returns-and-clears, 30s polling, the audio behaviour (per batch + once on launch), the separation from the on-launch inbox, and the click-through navigation. Note that notification producers remain centralized in `Notification::deliverTo()`.
- [ ] **Step 13 — Tests** `tests/Feature/RealtimeNotificationTest.php`:
  - Delivering a notification sets the recipient's flag; a recipient who opted out via preferences is not flagged/delivered.
  - Heartbeat returns unread items and clears the flag; a second heartbeat returns empty.
  - Heartbeat with the flag false returns empty and performs no content query.
  - A user without `view_own_notifications` is forbidden.
  - Task creation notifies each assignee (flag set) and not the actor.
  - Contract generation notifies the applicant's admission employee.
  - Permission isolation: a user who should not receive a notification receives none and their flag stays false.
- [ ] **Step 14 — Validate:** `php artisan test --filter=RealtimeNotificationTest`; full `php artisan test`; `npm run typecheck`; `npm run build:icons` (confirm no new unavailable-token warning). Record exact commands/results in §9.

## 7. Validation plan and results

- [ ] `php artisan test --filter=RealtimeNotificationTest` — planned.
- [ ] Full `php artisan test` — planned (baseline 379 passing).
- [ ] `npm run typecheck` — planned.
- [ ] `npm run build:icons` — planned (only if any class-based icon token is added).
- [ ] Manual check: two logged-in sessions; create a task assigned to the other user; confirm their heartbeat returns it within ~30s, the ding plays once, and clicking navigates.
- **Results:** not run — planning-only task; no code changed.
- **Checks not run and why:** MySQL application of the migration and browser audio autoplay behaviour are environment-dependent; both are called out in §8.

## 8. Risks and mitigations

- **Browser autoplay policy** may block audio before user interaction — mitigation: `play().catch()`; the launch ding runs after authentication (login involved interaction).
- **Flag stuck true** if a user never polls — acceptable (one extra fetch later); the heartbeat drains and clears on first poll.
- **Concurrent heartbeats** could double-deliver — mitigation: transaction + `lockForUpdate` on the user row before read-and-clear.
- **Route ordering** — the heartbeat route must be registered before `apiResource('notifications')`, otherwise `{notification}` model binding will 404 it; step 5 places it explicitly.
- **Existing `VisaFirmComplianceTest`** expects current `contractGenerated` recipients — mitigation: add the admission employee without removing existing recipients, and run the full suite.
- **Permission leakage** — recipients are already permission/role-scoped by producers; add the explicit isolation test (step 13).
- **Migration only validated on SQLite** — plain `Schema::table` with standard types; note that the owner must run it on MySQL.
- **Heartbeat adding items to the inbox** could clash with a simultaneous 60s inbox reload — mitigation: merge by id (prepend, de-dupe) instead of assigning the array.

## 9. Progress log

- 2026-10-13 — DISCOVERY: mapped the existing notification tables/models/controller/resource, `deliverTo` choke point, preference keys, task-assignment and payment-request (PendingTransaction) flows, contract generation, permission catalog and `finance_officer` grants, layouts that mount `NavBarNotifications`, build/test conventions, and confirmed no websocket stack exists (heartbeat polling). Located `notification-ding.mp3` at the repo root (not web-served).
- 2026-10-13 — CLARIFIED: user chose (1) contract-line creation → notify admission employee, (2) payment-request notification already viable so no new one, (3) ensure task-assignment notifications, (4) 30s heartbeat.
- 2026-10-13 — PLAN: full plan saved (this file), status `PLANNED`. No code changed (planning-only request).

## 10. Completion summary

- **Delivered:** discovery findings + finalized plan. No code changed.
- **Acceptance criteria:** not yet satisfied (planning-only).
- **Tests/checks passed:** none run.
- **Remaining / follow-up:** implement steps 1–14 when approved; optionally revisit the payment-request audience (`approve_pending_transactions` vs `view_pending_transactions`) as a separate change.
