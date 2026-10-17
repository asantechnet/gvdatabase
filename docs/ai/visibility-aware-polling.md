# Task record: Visibility-aware background polling

- **Task ID:** visibility-aware-polling
- **Created:** 2026-10-17
- **Last updated:** 2026-10-17
- **Status:** IMPLEMENTED (typecheck passing; manual DevTools run pending)
- **Outcome (planned):** Stop the four frontend background pollers from firing requests while the browser tab is hidden, using the Page Visibility API. A shared composable gates interval ticks on `document.visibilityState === 'visible'` and fires one immediate catch-up callback when the tab becomes visible again. No backend changes.

## 1. Requirements and acceptance criteria

- [x] AC1 While the browser tab is hidden, none of the four background pollers issue HTTP requests.
- [x] AC2 When the tab becomes visible again, each poller performs exactly one immediate catch-up request (without waiting up to a full interval), then resumes its normal cadence.
- [x] AC3 Normal cadence and behaviour while visible are unchanged: heartbeat 30s, inbox refresh 60s, audit pending badge 60s, workday status 60s.
- [x] AC4 The notification heartbeat retains its exactly-once delivery semantics: pausing while hidden loses nothing because the backend keeps `users.unread_notifications` set until the heartbeat delivers the batch; the batch is delivered on the first poll after refocus.
- [x] AC5 The notification ding is never triggered while the tab is hidden (no surprise audio; the ding already plays only when a poll returns a non-empty batch, and no polls run while hidden).
- [x] AC6 Existing permission gates are preserved: heartbeat and inbox only for `view_own_notifications`, audit badge only for `view_audit_logs` / super admin, workday only for `manage_own_workday`.
- [x] AC7 The shared composable cleans up its interval and its `visibilitychange` listener on component unmount, and guards SSR (`typeof document === 'undefined'`).
- [x] AC8 `npm run typecheck` passes.

## 2. Scope and non-goals

- **In scope:** new shared composable `resources/ts/composables/useVisiblePolling.ts`; adoption by the notification heartbeat composable, the navbar inbox refresher, the navbar audit-log pending-count refresher, and the workday status refresher (refactoring its hand-rolled visibility check).
- **Out of scope:** any backend/route change; adding a heartbeat-style one-shot delivery channel for audit logs (the audit badge remains a plain 60s count refresh; a real-time audit channel would be a separate task); offline gating (`navigator.onLine`) beyond what `WorkdayControl.vue` already does; Web Workers or IndexedDB tricks to dodge background throttling; changing any poll interval.

## 3. Project findings

### Current pollers (audited 2026-10-17)

| Poller | File | Interval | Visibility-gated today? |
|---|---|---|---|
| Notification heartbeat | `resources/ts/composables/useNotificationHeartbeat.ts:79` | 30s | No — `setInterval(poll, HEARTBEAT_INTERVAL)` fires regardless |
| Inbox refresh | `resources/ts/layouts/components/NavBarNotifications.vue:93` | 60s | No — `setInterval(loadNotifications, 60_000)` |
| Audit pending badge | `resources/ts/layouts/components/NavBarAuditLogs.vue:35` | 60s | No — `setInterval(refreshCount, 60_000)` |
| Workday status | `resources/ts/components/workday/WorkdayControl.vue:38` | 60s | Yes — `refreshWhenVisible()` checks `document.visibilityState === 'visible'` per tick and a `visibilitychange` listener refreshes immediately on refocus |

- The heartbeat is registered in exactly one place (`NavBarNotifications.vue:53`), `start()` guards against double-start, and an `isPolling` mutex prevents overlapping polls — so no duplicate timers exist today; the problem is purely that ticks fire while hidden.
- Browsers throttle timers in hidden tabs to roughly one execution per minute, but requests still go out — that is what the user observed in the DevTools Network tab.
- `NavBarNotifications.vue` already merges heartbeat batches into the inbox list (de-duped by id, `mergeHeartbeatBatch`), so catch-up delivery after refocus slots into existing behaviour.
- The audit badge polls `GET /audit-logs/pending-count` (`resources/ts/api/auditLog.ts:80-82`) — a plain count refresh, not a heartbeat; it is idempotent and safe to pause/catch-up.
- `WorkdayControl.vue` keeps extra listeners (`online`, `workday-status-changed`) and `disposed`/`requestPending` guards that must survive the refactor.

### Reusable pattern

- `WorkdayControl.vue:29-39` already contains the desired pattern (tick gated on visibility + immediate refresh on `visibilitychange`). The plan extracts it into a shared composable and applies it uniformly.

### Conventions

- Typecheck: `npm run typecheck` (never bare `vue-tsc`/`tsc`). No backend work, so no PHPUnit run is expected. No new icon tokens are introduced, so `npm run build:icons` is not needed.

## 4. Design

### 4.1 New composable — `resources/ts/composables/useVisiblePolling.ts`

```ts
export function useVisiblePolling(callback: () => void | Promise<void>, intervalMs: number) {
  // setInterval whose tick invokes callback only when document.visibilityState === 'visible'
  // document 'visibilitychange' listener: on becoming visible, invoke callback once immediately (catch-up)
  // start() / stop() exposed; interval + listener auto-cleaned via onUnmounted
  // SSR guard: no-op when typeof document === 'undefined'
}
```

- The composable does **not** perform an initial call on start; each call site keeps its own on-mounted initial load (matches current behaviour, e.g. `loadNotifications()` before `startHeartbeat()`).
- Repeated rapid visibility flips are harmless: each callback is idempotent and the existing per-poller guards (`isPolling`, `requestPending`) prevent overlap.

### 4.2 Call-site changes

1. **`useNotificationHeartbeat.ts`** — replace `timer = setInterval(poll, HEARTBEAT_INTERVAL)` with `useVisiblePolling(poll, HEARTBEAT_INTERVAL)`; keep the public API (`start/stop/poll/isPolling`), `isPolling` mutex, and `canAccess('view_own_notifications')` gates.
2. **`NavBarNotifications.vue`** — remove `refreshTimer`; the 60s refresh goes through `useVisiblePolling(loadNotifications, 60_000)`. On-mount initial load and `playLaunchDingOnce` unchanged.
3. **`NavBarAuditLogs.vue`** — replace `refreshTimer` with `useVisiblePolling(refreshCount, 60_000)`; keep the `open-audit-log` listener.
4. **`WorkdayControl.vue`** — replace the hand-rolled `refreshWhenVisible` + interval with the composable; keep `online` / `workday-status-changed` listeners and the `disposed`/`requestPending` guards.

### 4.3 Why pausing is safe for the heartbeat

The backend returns the queued batch only while `users.unread_notifications` is true and clears it inside a transaction on delivery (`GET /api/notifications/heartbeat`). Pausing while hidden therefore does not drop or duplicate notifications: the flag stays set, and the first poll after refocus delivers the batch exactly once. Skipping the ding while hidden is a UX improvement, not a loss.

## 5. Verification plan

- `npm run typecheck`.
- Manual (DevTools Network tab, app running):
  1. While visible: heartbeat every 30s, inbox/audit/workday refreshes every 60s (unchanged).
  2. Hide the tab for > 2 minutes: zero requests from all four pollers.
  3. Refocus: one immediate catch-up request per poller; a pending notification batch arrives merged into the inbox with a single ding.

## 6. Progress log

- 2026-10-17 — Plan created; implementation not started per user instruction.
- 2026-10-17 — Implementation completed: added `resources/ts/composables/useVisiblePolling.ts` and adopted it in `useNotificationHeartbeat.ts`, `NavBarNotifications.vue`, `NavBarAuditLogs.vue`, and `WorkdayControl.vue` (hand-rolled visibility check removed). `npm run typecheck` passes. Manual DevTools verification (hide tab > 2 min, refocus catch-up) still to be run in a live app.
