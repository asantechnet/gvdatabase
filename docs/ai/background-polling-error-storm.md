# Task: Stop inactive background polling and notification error storms

- Created / updated: 2026-10-19
- Status: PARTIALLY COMPLETED (source/docs implemented; production build/typecheck/browser verification blocked)
- Outcome: leaving an applicant form to research in another tab/window must not accumulate background network-error toasts or cause catch-up request bursts.

## Requirements and acceptance criteria

- [x] No new background notification, audit, inbox/count, workday or version reads while hidden, unfocused or browser-reported offline, including initial loads and event-driven entry points (source and deterministic tests).
- [x] Resume with one catch-up per poller after returning to the active online app, coalesce rapid events/in-flight requests, and schedule the next interval after completion. Preserve normal 30s heartbeat, 60s inbox/count/workday and 15-minute version delays.
- [x] Background errors never emit global API error toasts; user-initiated form/mutation errors retain existing feedback (interceptor regression tests).
- [x] Bounded requests and no immediate retry loop; transient background refresh-token failures preserve the session/form route (regression tests).
- [x] Already-dispatched heartbeat reads are not canceled on blur; successfully returned late batches are deferred within the same token/session. No new hidden ding or audit notice (regression tests). Token rotations and transport-after-commit loss are explicitly documented limits.
- [x] Stop/unmount removes timers/listeners and cannot restart when pending work finishes; disposed heartbeat public poll calls cannot dispatch.
- [x] Preserve permissions, delivery choke points, cache exclusions, response normalization and earlier uncommitted PWA/favicon/documentation work (source review; no backend/worker strategy change).
- [x] Save meaningful regression tests and update maintained facts/history with actual validation limits.
- [ ] Production typecheck/build, generated artifact refresh and real-browser form/focus/offline acceptance.

## Scope and non-goals

Frontend polling scheduler/activity predicate; explicit background-read API config; Axios cancellation/error handling and background authentication retry boundaries; notification/audit/navbar/workday/version callers; focused tests and documentation. No backend delivery-protocol rewrite, websocket, Background Sync, blanket suppression of manual-action errors, clearing unrelated toasts or global change to toast expiry.

## Investigation and dependency/impact review

Used repository searches and traced call/data flows (no graph tool is available): navbar components -> heartbeat composables/inbox/count APIs -> Axios request/response interceptors -> api-error event -> ApiErrorNotifier -> vue-toastification. Inspected workday event handlers and version polling; located every useVisiblePolling and network setInterval outside template internals. Checked notification/audit heartbeat server semantics from project instructions and preserved NetworkOnly exclusions. Reviewed the old visibility-aware-polling task record, current scheduler, earlier PWA facts/plan and uncommitted working-tree changes.

Observed gaps: local catches occur after global interceptor notifications; background GET APIs do not opt out of global errors; scheduler checks visibility only and has no shared concurrency/focus/offline gate; mount loads and workday events bypass it; late heartbeat success can ding/show audit notices after blur; the notifications async mounted handler can start pollers after unmount; 401 refresh can run after blur and a transient refresh failure logs out the session. There is one _retry auth bound, not evidence of an unbounded recursive polling loop. Browser-level ERR_NETWORK diagnostics cannot be suppressed by catching errors and inactivity does not universally prohibit networking.

## Decisions / clarification gate

- The request is sufficiently clear; no blocking product question remains. "Active" means visible, focused and online; moving to another window pauses polling even if this tab remains visible.
- Keep user-triggered API behavior intact. Background traffic gets explicit typed options and existing quiet headers, with a second dispatch-time guard in Axios so auth replays cannot bypass activity policy.
- Preserve dispatched heartbeat results rather than aborting delivery reads. A network timeout/transport loss after server commit remains an existing acknowledgment limitation; do not claim exactly-once end-to-end transport.
- Keep all previous task changes. New facts belong in docs/facts/background-polling.md and the old task is labeled as superseded.

## Implementation plan

- [x] 1. Add shared active/online predicate and typed background-read config (quiet + 10-second timeout). Harden useVisiblePolling: completion-based timer, no overlap, focus/visibility/online/offline/page lifecycle listeners, guarded initial/manual run and cleanup/disposal.
- [x] 2. Apply API-layer background intent to heartbeat/inbox/count/workday/version reads. Add dispatch/401-retry activity gates and silent cancellations in Axios. Preserve terminal auth handling while treating transient background token refresh failures as retryable.
- [x] 3. Route navbar mounts and workday events through the scheduler, keep badge/inbox data on failures, and defer already-returned heartbeat batches/notices until active. Preserve permission guards and launch chime intent.
- [x] 4. Add deterministic tests of real scheduler/modules/interceptors: hidden/unfocused/offline starts, rapid catch-up events, slow requests, errors, unmount, late heartbeat batches, quiet timeout/network/HTTP failures, manual-action error controls and auth replay boundaries.
- [ ] 5. Run focused regressions and existing PWA tests, npm run typecheck/build where possible; inspect diff and record environmental blocks accurately. No hand-edited generated outputs.
- [x] 6. Document maintained polling facts and update facts/AI indexes, PWA polling description, AGENTS.md and historical task banner. Record outcomes here as work proceeds.

## Validation strategy

Node deterministic tests execute actual TypeScript through the native Node transform (installed TypeScript fallback where available), fake activity/timers and Axios hooks. Verify no error events for explicitly background reads while foreground mutations still emit events; verify no false delivery discard on blur and no remount leaks. Run existing npm run test:pwa-updates to catch shared-helper integration regressions. Attempt npm run typecheck and npm run build in the supported environment; previous turn found Linux-installed dependencies inaccessible from Windows and WSL escalation failed. Real-browser acceptance: leave an unsaved applicant form open, hide/unfocus >2 minutes, simulate offline/server errors, return and verify zero queued error toasts, one catch-up per eligible poller and preserved input; repeat with rapid focus changes, pending heartbeat and logout/unmount. Report browser checks separately from simulation.

## Risks and mitigations

- Strict focus gate pauses with DevTools or another window focused: intentional policy for this request; document it.
- Background transport failures do not prove server delivery flag remained unchanged: retain successfully returned batches, avoid cancellation-on-blur, and document protocol limitations.
- Other tabs and browser/PWA worker activity exist independently: this change stops application pollers, not all browser network tasks.
- Existing installed clients still run old code until a successful frontend build/deploy/update; keep source and generated-artifact status explicit.

## Progress log

- 2026-10-19 DISCOVERY: traced all six scheduler consumers, direct loads and shared error/auth paths. Preserved existing uncommitted PWA work. Reviewed browser visibility and Axios cancellation references.
- 2026-10-19 PLAN: full plan saved before implementation; no material clarification required.
- 2026-10-19 IMPLEMENTATION: added active/online dispatch policy, quiet bounded background API reads, completion-based scheduler with focus/lifecycle gates, and initial/event call-site routing. Dispatched heartbeats are retained rather than canceled on blur, with foreground deferred delivery and session-token isolation. Canceled/paused requests never emit api-error; background refresh-token transport failures no longer clear the session.
- 2026-10-19 VALIDATION: npm run test:background-polling passes 16 cases against actual scheduler, queue, both heartbeat composables, four API contracts and Axios hooks; npm run test:pwa-updates passes all 17 existing cases after adapting its version-poller integration fixture to guarded startup. Tests explicitly cover normal manual errors remaining visible and transient versus terminal refresh failures.
- 2026-10-19 ENVIRONMENT: npm run typecheck failed with MODULE_NOT_FOUND for vue-tsc/bin/vue-tsc.js; npm run build failed because the Linux vite shim is not executable from Windows. Attempted WSL escalation for both commands; process launch failed with helper_unknown_error: setup refresh had errors. No dependencies or generated assets were replaced and no production deployment was performed. Browser acceptance remains unrun.
- 2026-10-19 DOCUMENTATION/REVIEW: added docs/facts/background-polling.md, updated indexes/PWA facts/AGENTS.md, and clearly superseded the historical visibility-only plan. Documentation link targets exist; scoped git diff --check passes. Reviewed all six callers and kept previous task changes intact. No new translation strings or backend behavior changes require separate PHP/i18n coverage for this task.

## Completion summary

Source fix, regression coverage and maintained behavior documentation are delivered. The original toast storm came from background API errors emitting global events before local catches, compounded by incomplete activity/direct-load/concurrency policy. Polling now enforces activity at scheduler and dispatch boundaries, and background failures are quiet without hiding manual-action errors.

Passed: 16 polling tests + 17 PWA tests (33 total), scoped diff whitespace checks and documentation links. Pending: production typecheck/build, refreshed generated production assets and real-browser form-preservation/inactive-network checks. Keep this task partially completed until those operational checks can be run. Deploying source alongside old public/build and sw.js will continue serving the older behavior.
