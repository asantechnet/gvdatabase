# Task: PWA update reliability and authoritative documentation

- Task ID: pwa-update-reliability-and-facts
- Created / last updated: 2026-10-19
- Status: PARTIALLY COMPLETED (source and documentation implemented; production verification/build blocked)
- Outcome: reliable, consent-based updates and an independently maintained PWA facts reference.

## Requirements and acceptance criteria

- [x] No update toast for the running version, ordinary first installation, failed identity checks or an unready installation (lifecycle regression coverage).
- [x] Detect real deployments at launch, every 15 visible minutes and on return to visibility; explicitly check the worker too (composable/lifecycle coverage; real-browser run pending).
- [x] Activate only a verified, installed waiting worker after user acceptance; reload only after that worker controls this tab; retain retry on failure (lifecycle coverage).
- [x] Keep other open tabs/forms from automatically reloading; prevent overlapping checks and duplicate clicks/toasts (lifecycle coverage; unsaved-form browser check pending).
- [x] Clickable update toast uses cursor: pointer and remains retryable after errors (source review; rendered CSS check pending).
- [x] Preserve offline read-only strategies, notification exclusions, authentication choke point, print behavior and dynamic branding in source; PHP manifest tests pass. Full browser offline/print verification pending.
- [x] Add complete docs/facts PWA reference, documentation indexes and historical labels; preserve all existing document paths and docs/ai.
- [x] Add meaningful update lifecycle regression tests, attempt typecheck/build and record actual results and environmental limitations.
- [ ] Complete production typecheck/build, refresh generated artifacts, and run real-browser two-release/offline checks in an operational dependency environment.

## Scope and non-goals

PWA registration, update/version orchestration, worker messaging/activation, toast, deployment headers, focused tests and documentation. No websocket, mutation queue, unrelated feature changes or deployment workflow activation. Preserve the pre-existing favicon.svg edit from the earlier user request.

## Findings and impact analysis

Repository search and source inspection were used in place of unavailable graph tools. Traced App.vue -> useAppVersion -> API contract -> Axios normalization -> public /api/version controller -> public/version.json; plugin registration -> Workbox events -> PwaUpdateNotifier -> worker message/activate handlers; worker cache strategies -> offlineCache -> authStorage. Reviewed Vite generation, Blade manifest links, public/.htaccess, existing PWA feature tests, historical PWA/visibility/deployment records. No server state has been inspected.

Current flaws: version mismatch and any waiting event both set the same boolean; no waiting worker identity check; update checks are not periodic at worker level; clicks reload without waiting for installation; unconditional controlling handler reloads other tabs; 1.5-second timer can reload prematurely; clients.claim runs from message handler before activation completes; offline-ready is raised at registration instead of completed install; failures are silent. Same-version regenerated workers and stale/inconsistent artifacts can produce misleading prompts. These are code paths, not a proven diagnosis of the user's production server.

## Decisions and assumptions

- User chose to preserve document paths and add indexes and historical labels.
- Show an update only when a completed waiting worker identifies the same release reported by the server and that release differs from the current bundle. Version strings are identities, not ordered numbers (rollbacks are supported).
- Keep the established package-version + build-commit identity. Rebuilding identical source on the same commit should not trigger an update prompt; never edit generated version files by hand or compare deployed HEAD to a prebuilt bundle's build commit.
- First installation must never prompt for an update or reload. A legacy worker without version messaging is not sufficient evidence to prompt; a newly deployed worker supports the protocol.
- Keep updates user initiated. A worker may activate naturally after all old clients close, but no tab may automatically reload without its own click.
- No material product questions remain; documentation placement was resolved before affected changes.

## Implementation plan

- [x] 1. Centralize update readiness and activation in the PWA plugin; version messaging with bounded waits, native registration state listeners and explicit periodic checks coordinated by useAppVersion. Guard asynchronous races and version equality, handle failures with diagnostics and retry state.
- [x] 2. Change worker messaging to return build identity and claim clients during activate; remove early claim and timer-based reload. Show offline-ready only after observing a completed active worker, once per storage marker.
- [x] 3. Make stale toast state dismissible/retryable with a pointer cursor; preserve translations and existing RTL/theme support. Add appropriate no-cache worker/version headers without disturbing Laravel rewrite rules.
- [x] 4. Add deterministic lifecycle tests against actual modules with fake worker/registration/channel/timers: unchanged version, first install, installation in progress, stale workers, legacy messaging, slow activation, duplicate clicks, other tabs, failures and retries.
- [ ] 5. Run focused tests, npm run typecheck, relevant PHP PWA tests and npm run build (generated artifacts must match). Review actual outcomes before documenting final behavior.
- [x] 6. Write docs/facts/pwa.md plus facts/docs/ai indexes; label historical PWA/deployment records, link the source of truth from AGENTS.md and README.md, preserve all paths. Include lifecycle, caching, user isolation, branding, manual deploy, diagnosis, limits and maintenance requirements.
- [x] 7. Review final diff, document completed acceptance criteria and validation limitations here.

## Validation plan

Deterministic Node regression suite using TypeScript transpilation (existing repository pattern); PHP PwaManifestTest; npm run typecheck (never bare tsc/vue-tsc); production npm run build. Real browser acceptance: deploy build A and then B over HTTPS/localhost, keep two tabs with unsaved input, confirm one persistent ready toast, accept in one tab and verify only it reloads; repeat same-version deployment and failed asset fetch and confirm no false prompt. Browser testing requires an operational production build/runtime and will be reported separately from simulated lifecycle checks.

## Risks and mitigations

- Old deployed clients retain their old code until updated; document the transition and preserve SKIP_WAITING compatibility.
- Partial deployments or blocked assets cannot be repaired client-side: require matching build/worker/version artifacts and no-cache release metadata; don't advertise unready releases.
- A waiting worker may become obsolete or redundant: revalidate identity at click and retain retry without forced reload.
- Dependencies currently appear installed for Linux; WSL access previously failed. Attempt supported commands, request escalation for sandbox failures, record actual limitations rather than claiming checks pass.

## Progress log

- 2026-10-19 DISCOVERY: inspected call/data flow and historical documentation; working tree contains only the pre-existing favicon.svg modification.
- 2026-10-19 PLAN: full implementation and validation plan saved before implementation. User confirmed preservation of existing doc paths.
- 2026-10-19 IMPLEMENTATION: centralized readiness in PwaUpdates, added GET_VERSION messaging, explicit worker update checks, per-tab activation consent and a 30-second retryable timeout instead of forced reload. Moved claim into activation; corrected first-install offline-ready timing. Added toast cursor, stale-toast dismissal, translated activation failure, version response validation/10-second HTTP timeout, and worker/version no-store headers.
- 2026-10-19 VALIDATION: npm run test:pwa-updates passes 13 lifecycle cases using Node's native TypeScript transform; tests also support installed TypeScript on older Node. php artisan test --filter=PwaManifestTest passes 4 tests / 29 assertions, including no-store version response.
- 2026-10-19 ENVIRONMENT: npm run typecheck fails because Windows cannot resolve vue-tsc from the Linux dependency install. npm run build fails because the Linux-only vite shim is not executable from Windows. WSL escalation failed to launch with helper_unknown_error (setup refresh had errors); attempting direct compiler access also failed with EPERM and escalation failed with the same helper error. Generated production assets remain unchanged; do not deploy source fixes with the old generated build.
- 2026-10-19 REVIEW: caught and covered controllerchange arriving while the worker is still activating; wait for both state and controller before reloading. Fixed superseded server-version response races and covered the case in a regression test. Real worker tests verify GET_VERSION, SKIP_WAITING, activation-only claim, cache cleanup and preserving the waitUntil method receiver.
- 2026-10-19 DOCUMENTATION: added docs/facts/pwa.md and its maintenance index, docs/README.md and docs/ai/README.md. Preserved all document paths, indexed root documents as historical/unverified where appropriate, added banners to old PWA/deployment/visibility task records, and updated AGENTS.md/README.md to point future agents to the facts. Corrected historical public/icons references to actual root icon paths.
- 2026-10-19 VALIDATION: latest npm run test:pwa-updates passes 17 cases; PHP PWA suite remains 4 passed / 29 assertions. Documentation relative links resolve and all PWA toast translation keys exist in valid en/fa-AF/ps-AF JSON. npm run i18n:check cannot resolve @vue/compiler-sfc in this dependency environment; full translation audit is not claimed. No build output was hand-edited and no dependency install or production deployment was performed.

## Completion summary

Delivered source fixes and the maintained PWA behavior contract with regression coverage and preserved documentation history. The implementation plan, decisions and progress are all in this record.

Passing: 17 lifecycle/composable/API/worker tests; 4 PHP tests (29 assertions); targeted translation JSON/key checks; documentation link checks. Remaining: npm run typecheck, npm run i18n:check, npm run build, generated production artifact refresh, and real browser two-release/offline/print checks. Production bundles still contain the previous implementation until successfully rebuilt. Task is explicitly partially completed because those required checks and generated assets could not be completed in this environment.
