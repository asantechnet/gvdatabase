# Background polling, activity and error feedback

- Maintained source behavior, reviewed 2026-10-19.
- Implementation/progress: [background-polling-error-storm](../ai/background-polling-error-storm.md).
- Validation: 16 deterministic polling/API/interceptor tests and the 17 PWA regression tests pass. Production typecheck/build and real-browser acceptance are blocked by the current Linux-installed dependencies being inaccessible from Windows; WSL escalation failed to launch. Existing generated production files have not been rebuilt. This reference describes the source changes, not a verified live deployment.

## Required behavior

Leaving an applicant form to research in another tab or window must not accumulate notification/audit network-error toasts. Background refreshes must start only while the educational app is visible, focused and online. Background failures are quiet; normal user-triggered save, validation and permission errors still use the existing feedback.

The application uses HTTP polling, not sockets, recursive immediate retry loops or a background mutation queue. Browser inactivity does not universally prohibit HTTP: browsers may throttle, freeze or suspend background pages. The application enforces its own stronger activity rule. [Platform visibility reference](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API).

## Activity policy and scheduler

[backgroundActivity.ts](../../resources/ts/utils/backgroundActivity.ts) defines activity as all of:

1. A browser document exists and `document.visibilityState === 'visible'`.
2. `document.hasFocus()` is true.
3. The browser does not report `navigator.onLine === false`.

Focus is intentional: another app/window, a browser address bar or DevTools can take focus while the page remains visible. Polling pauses in those circumstances too. `navigator.onLine` is a hint, not proof of server reachability; active requests may still fail quietly.

[useVisiblePolling.ts](../../resources/ts/composables/useVisiblePolling.ts) owns scheduling:

- `start()` schedules the first normal interval; `start(true)` attempts an immediate gated initial load. Mount handlers must use this mechanism rather than making an unguarded request first.
- `run()` supports event-driven refreshes with the same activity, disposal and concurrency gates. Workday status-change events use this entry point.
- One callback runs at a time per scheduler. The next timeout starts after its promise settles, so the cadence is the interval plus request duration, rather than a wall-clock fixed interval.
- Visibility/focus and online/offline changes reconcile activity. Inactivity removes scheduled timeouts. Becoming active starts one catch-up; rapid events while already active or while a request is pending do not start overlapping work.
- `pagehide` pauses scheduling, and `pageshow` reconciles it for back/forward-cache restoration.
- Stop/unmount removes timeout and all listeners. Unmounted schedulers cannot restart, even if an older asynchronous load finishes later.
- Rejected callbacks do not retry recursively. They settle and retry after the normal delay while eligible. The scheduler logs unexpected callback failures; it never emits a UI error toast itself.

This is a per-poller, per-tab policy. On return, several distinct eligible pollers may each make one request. It is not a single combined request across all pollers or a leader-election mechanism across tabs.

| Poller | Normal delay after completion | Initial behavior |
| --- | --- | --- |
| Notification heartbeat | 30 seconds | Start interval; immediate catch-up when returning to activity |
| Audit heartbeat | 30 seconds | Start interval; immediate catch-up when returning to activity |
| Navbar notification inbox | 60 seconds | Gated immediate load |
| Navbar pending audit count | 60 seconds | Gated immediate load |
| Workday status | 60 seconds | Gated immediate load; status-change events also gated |
| PWA version and worker check | 15 minutes | Gated immediate check; update readiness still requires a verified worker |

Existing permission checks remain with each producer. Do not use the scheduler as a substitute for API authorization.

## API intent and global toasts

[backgroundRead.ts](../../resources/ts/api/backgroundRead.ts) supplies typed opt-in options for background reads. `backgroundReadConfig({ background: true })` applies:

```text
timeout: 10000 ms
X-Background-Request: true
X-Suppress-Error-Notification: true
```

Heartbeats and version checks always use that configuration. Navbar inbox/count reads opt in; the full notification page's manual load and notification mutations do not. `WorkdayApi.status(true)` is the quiet background read; `status()` in user-triggered workflows retains normal error handling.

The Axios request interceptor checks activity immediately before dispatch for marked requests, including authenticated replays. If no longer active, it rejects as `CanceledError` before a network request is dispatched. Its response interceptor handles cancellations silently, and the quiet header suppresses global `api-error` events for HTTP, network, timeout and offline failures. Request-level validation and permission checks already honor the quiet header.

This fixes the old ordering bug: a local `catch` in a poller cannot prevent a toast that the shared interceptor already emitted. Background intent must be declared in the API layer before dispatch. Do not globally disable `ApiErrorNotifier`, discard all errors while hidden, or change global toast focus/expiry to hide this bug; those approaches can conceal actual save failures.

The browser can still print low-level network errors in DevTools for requests that were already sent or active failed requests. The application cannot suppress those browser diagnostics. It prevents new inactive poll dispatches and application-generated toast piles. Other traffic, such as a user save already in progress or service-worker precache installation, is outside the poller scheduler.

## Authentication retry boundaries

- The existing shared refresh-token promise remains in place, and `_retry` permits only one replay of a protected request.
- A background 401 received after the tab becomes inactive does not start renewal; a future eligible poll can retry.
- An already-dispatched renewal is allowed to finish because refresh tokens are single-use. A protected replay then passes through the request activity gate and is canceled if inactive.
- Refresh calls have a 10-second timeout. For a background request, transport/timeout failure, HTTP 429 or HTTP 5xx from renewal does not clear authentication or redirect away from an unsaved form. Retry occurs through a later normal poll.
- Terminal renewal rejection, such as HTTP 401/403, retains existing session-clearing/login behavior. Ordinary user-initiated request handling is otherwise preserved; this is not a blanket authentication-error suppression mechanism.

## Heartbeat responses arriving after blur

Notification heartbeat requests clear a server delivery flag; audit heartbeat requests advance a server cursor and clear a flag. Canceling an already-dispatched request on blur can discard a successfully committed batch, so these reads are not actively aborted on inactivity.

[useForegroundBatch.ts](../../resources/ts/composables/useForegroundBatch.ts) retains successful late responses in memory, deduplicated by ID. If the page is inactive, it does not call the notification merge/chime callback or the audit notice callback. The next active heartbeat poll drains retained items once before requesting the next batch. A success arriving after the user has already returned is delivered then. One notification ding or audit notice is emitted per delivered batch, never per row.

The queue is component-local and tied conservatively to the access token captured at dispatch. It is cleared on unmount or token change; a response from an older token is discarded. This avoids presenting one account's queued data to another account. It also means a token rotation can discard a deferred notice; this is not a durable notification delivery queue. Notification inbox reads and audit count/list reads remain independent authoritative views.

Failures/timeouts can occur after a server delivery transaction commits. The existing protocol has no client acknowledgment/replay mechanism, so it cannot promise lossless end-to-end delivery under transport loss. Pausing requests before dispatch is safe; do not claim every failed request leaves the flag untouched.

Navbar inbox/count failures preserve their last successful data instead of clearing the UI. The launch chime is attempted only on a successful active inbox load. No new hidden-tab notification chime is initiated. Poller disposal blocks late heartbeat presentation and future exported poll calls.

## Implementation owners

| Area | Source |
| --- | --- |
| Activity and scheduling | [backgroundActivity.ts](../../resources/ts/utils/backgroundActivity.ts), [useVisiblePolling.ts](../../resources/ts/composables/useVisiblePolling.ts) |
| API intent and error/auth interception | [backgroundRead.ts](../../resources/ts/api/backgroundRead.ts), [axios.ts](../../resources/ts/api/axios.ts), [ApiErrorNotifier.vue](../../resources/ts/components/ApiErrorNotifier.vue) |
| Heartbeats and deferred responses | [useNotificationHeartbeat.ts](../../resources/ts/composables/useNotificationHeartbeat.ts), [useAuditHeartbeat.ts](../../resources/ts/composables/useAuditHeartbeat.ts), [useForegroundBatch.ts](../../resources/ts/composables/useForegroundBatch.ts) |
| Inbox and audit badge | [NavBarNotifications.vue](../../resources/ts/layouts/components/NavBarNotifications.vue), [NavBarAuditLogs.vue](../../resources/ts/layouts/components/NavBarAuditLogs.vue) |
| Workday and PWA callers | [WorkdayControl.vue](../../resources/ts/components/workday/WorkdayControl.vue), [useAppVersion.ts](../../resources/ts/composables/useAppVersion.ts) |
| API contracts | [notification.ts](../../resources/ts/api/notification.ts), [auditLog.ts](../../resources/ts/api/auditLog.ts), [workday.ts](../../resources/ts/api/workday.ts), [appVersion.ts](../../resources/ts/api/appVersion.ts) |
| Regression coverage | [background-polling-test.cjs](../../scripts/background-polling-test.cjs), [pwa-updates-test.cjs](../../scripts/pwa-updates-test.cjs) |

Notification/audit heartbeat routes remain NetworkOnly in the service worker. Backend delivery semantics, roles and permissions were not changed. See [PWA facts](pwa.md) for caching, manual build/deploy and consent-based updates.

## Verification and diagnosis

Run `npm run test:background-polling` and `npm run test:pwa-updates`, then `npm run typecheck` and `npm run build` in a working dependency environment. The deterministic tests exercise real scheduler, heartbeat, queue, API-contract and Axios interceptor code with fake clock/activity/transport boundaries; they are not a production browser test or semantic typecheck.

For browser acceptance, use a successful production build over HTTPS/localhost:

1. Keep an unsaved applicant form open, then switch to another tab/window for at least two minutes. Confirm no new poll requests start after inactivity and no background network-error toasts appear. A request already in flight may settle.
2. Restore focus/visibility: each permitted poller catches up once, then resumes after completion plus its normal delay. Rapid focus/online events must not replay every missed tick or overlap callbacks.
3. Repeat offline, with server errors/timeouts and during token renewal. Returning must not reveal an accumulated error-toast stack or lose the form through a transient background renewal failure. A real user save failure must still display its normal feedback.
4. Delay a successful heartbeat response until after blur; confirm no hidden ding/audit notice, then one delivery on return. Repeat after logout/unmount and verify nothing leaks into the next session.
5. Verify permissions, counts/inbox, valid notification/audit click navigation and PWA update readiness still work.

If inactive requests persist after deployment, check that the running JavaScript is the newly built release, inspect the request's Initiator stack and background headers, and inspect visibility/focus/online state. A stale installed PWA can continue executing the old helper until updated. Old toasts already created by that code are not retroactively cleared by a server deploy.
