# PWA, caching and application updates

- Maintained reference, reviewed 2026-10-19.
- Applies to the source implementation accompanying [the reliability task](../ai/pwa-update-reliability-and-facts.md).
- Validation: 17 deterministic frontend lifecycle tests and 4 PHP PWA tests passed. Production typecheck/build, generated artifact refresh and real-browser two-release testing remain blocked in the current Windows/Linux dependency environment. The checked-in generated bundle still has the previous behavior until a successful build is performed. This document describes the updated source behavior, not an assertion about a live deployment.

## Behavior that must be preserved

- Installation requires a secure origin (HTTPS, with localhost as a development exception), a valid manifest and working service-worker/static-asset endpoints.
- Production uses one worker at `/sw.js` with scope `/`. The installed app and browser tabs use the same origin and registration. There is no separate app-store binary or automatic Git pull on a user's device.
- Offline operation is read-only. No mutation queue or Background Sync exists.
- Only a ready worker whose release matches the last successfully checked server release, and differs from the running bundle, can produce an update prompt. A mismatch alone is insufficient.
- The user accepts an update by clicking its toast. Only that tab reloads, after the selected worker is fully activated and controls that tab. Other tabs keep their forms and can accept separately.
- Same-version waiting workers, first installation, partial downloads and failed checks do not produce update prompts. Version identities are compared for equality; a different rollback release is eligible too.
- Cached API data is cleared through the existing authentication choke point and on accepting/booting a new release. Heartbeats must never be cached.
- Print styles and document sizing are not rewritten by the worker. Keep the A4/A5 namespaced printing workflow intact.

## Source map

| Responsibility | Implementation |
| --- | --- |
| Build identity, worker compilation, precache injection | [vite.config.ts](../../vite.config.ts) |
| Worker registration and shared reactive state | [plugins/pwa/index.ts](../../resources/ts/plugins/pwa/index.ts) |
| Readiness verification, coalescing, consent and activation | [utils/pwaUpdates.ts](../../resources/ts/utils/pwaUpdates.ts) |
| Version polling and boot-time cache invalidation | [useAppVersion.ts](../../resources/ts/composables/useAppVersion.ts), [useVisiblePolling.ts](../../resources/ts/composables/useVisiblePolling.ts) |
| Public version API and frontend response contract | [AppVersionController.php](../../app/Http/Controllers/AppVersionController.php), [api/appVersion.ts](../../resources/ts/api/appVersion.ts), [api/axios.ts](../../resources/ts/api/axios.ts) |
| Worker caching and messages | [service-worker/sw.ts](../../resources/ts/service-worker/sw.ts) |
| Toast and pointer styling | [PwaUpdateNotifier.vue](../../resources/ts/components/PwaUpdateNotifier.vue) |
| Manifest and application shell | [PwaManifestController.php](../../app/Http/Controllers/PwaManifestController.php), [application.blade.php](../../resources/views/application.blade.php), [routes/web.php](../../routes/web.php) |
| User isolation and cache purge | [authStorage.ts](../../resources/ts/utils/authStorage.ts), [offlineCache.ts](../../resources/ts/utils/offlineCache.ts) |
| Static icon generation | [pwa-assets.config.ts](../../pwa-assets.config.ts), [favicon.svg](../../public/favicon.svg) |
| Apache worker/version cache headers | [public/.htaccess](../../public/.htaccess) |
| Regression checks | [pwa-updates-test.cjs](../../scripts/pwa-updates-test.cjs), [PwaManifestTest.php](../../tests/Feature/PwaManifestTest.php) |

`App.vue` mounts the notifier and version composable once. The application's plugin registration loads the PWA plugin; pages must not create their own service-worker registrations or version pollers.

## Release identity and generation

`npm run build` computes `package.json.version + '+' + git rev-parse --short HEAD`, for example `9.5.0+8993ef0`. If Git is unavailable, it falls back to package version plus a timestamp. That fallback gives each build a different identity; preserve Git metadata when building repeatable releases.

The identity is embedded as `__APP_VERSION__` in the frontend and worker. The build also writes `public/version.json` containing `version` and `built_at`. The timestamp is informational and is not used to decide whether an update exists.

The public `GET /api/version` route reads that file, with `config('app.version', '0.0.0')` as a fallback if the file is missing. The response has `version` and `built_at` at the JSON root and a no-store header. The shared Axios interceptor wraps this into `response.data.data`; only the API contract unwraps it. That contract rejects missing, blank or non-string versions and sets a 10-second request timeout.

The version identifies the source commit used for the build. When generated assets are committed afterward, their build SHA is expected to differ from the final generated-assets commit. Do not rebuild repeatedly just to make the embedded SHA match that final commit: another asset commit would change HEAD again. Do not manually change version.json to the deployed HEAD.

Cloning, pulling, PHP cache commands and `npm run pwa:assets` do not generate a new frontend release. Build changed source from the intended committed source revision. Dirty builds on the same commit reuse the same identity and are not a supported way to publish distinct releases.

Deploy `public/build/**` (including its Vite manifest), `public/sw.js`, and `public/version.json` from one successful build as one release. Hand-editing any of them, mixing builds or publishing metadata before assets is unsupported.

## Update checks and readiness

1. In production, register `/sw.js` with `{ scope: '/', updateViaCache: 'none' }`. Registration is skipped under Vite development mode and when service workers are unsupported. Failures log `[PWA]` diagnostics and can be retried on a later version poll.
2. On app mount, while visible, focused and online, request `/api/version` as a quiet background read. Repeat 15 minutes after the previous check completes; returning to activity (focus/visibility/reconnection) triggers one catch-up. Inactive checks are skipped and in-flight checks coalesce. Shared scheduling and error-feedback rules are defined in [background polling facts](background-polling.md).
3. After a successful version response, explicitly call the registration's `update()`, including when the identity is unchanged. This checks the worker too, rather than relying only on browser lifecycle checks. Browser-initiated update checks can also run independently.
4. Observe `updatefound`, worker state changes and `controllerchange`. Installation must complete before it can qualify. A failed precache download prevents the new worker reaching readiness.
5. Select the registration's waiting worker, or its active worker if that worker already controls this tab (for example, after another tab accepted an update).
6. When the server identity differs from this bundle, ask the candidate `GET_VERSION` over a `MessageChannel`. The response must contain the identical release reported by `/api/version`. The response wait is bounded to 3 seconds. A legacy or unresponsive worker is not enough evidence; log a diagnostic once for that worker and suppress the prompt.
7. Publish readiness only if the candidate is still current and is installed/waiting or activated/controlling. Asynchronous inspections ignore superseded results. A return to the running version or replacement with an unverified candidate clears readiness and dismisses the stale toast.

The server identity means the last successful version check, not a continuously observed value. Failed API checks leave the previous state intact until a later successful check. The worker update check and inspections can also retry later; no failed check forces a reload.

There is no update toast solely because a worker file's bytes changed, a build timestamp changed, an icon was fetched, the browser restarted, or the application was installed. Rebuilding with a different Git SHA is a different release identity even if the user-visible changes were only documentation; avoid unnecessary rebuilds for documentation-only commits.

## Accepting an update

The translated toast uses `vue-toastification`, a stable ID, no timeout, no close button, no drag dismissal and `cursor: pointer`. It remains available while the release is ready. Saving work before clicking remains the user's responsibility.

On click:

1. Ignore duplicate clicks while applying or after this tab has already reloaded.
2. Reinspect candidate identity and readiness. A stale toast cannot activate a superseded worker.
3. If that worker already controls the tab and is activated, proceed directly to the accepted reload.
4. Otherwise listen for controller and worker-state changes, then send `SKIP_WAITING` to that exact waiting worker. The worker calls `skipWaiting()`; it does not claim clients inside the message handler.
5. During its activation, the worker finishes old-cache cleanup and calls `clients.claim()`. The client waits for both that worker to control this tab and its state to be `activated`; control may change while it is still `activating`.
6. Clear API caches and reload once. There is no timer that forces a reload before activation.

A 30-second activation timeout reports a translated failure and leaves the page open. The timeout is a failure boundary, not a reload fallback. Readiness is reassessed and a still-valid release can be retried. If another tab already activated it, the next inspection can offer the local reload without another `SKIP_WAITING`.

Activation is shared by all tabs on the origin; reload consent is per tab. Other tabs may switch to the new controller and older runtime caches are removed, but they are not automatically navigated/reloaded by the new client implementation. A worker can also activate naturally when no old controlled clients remain; that does not imply an unwanted update toast.

The first active worker produces the offline-ready success notice once per origin/browser-profile storage marker (`vuexy-pwa-offline-ready`), not once for every app launch or each OS installation. It is based on completed activation, not registration success. `vuexy-app-version` records the last running bundle for boot-time API cache invalidation. Restricted localStorage must not stop registration or polling.

## Cache behavior

Workbox uses the custom worker in `injectManifest` mode. The production build injects matching JS, CSS, SVG, PNG, ICO, font and webmanifest files under its `public` output root, including `build/assets/**` and bundled static icons. Files over 4 MiB are excluded by the configured precache limit. `/offline.html` and `/sounds/notification-ding.mp3` are explicitly revisioned with the release identity. Hashed filenames and Workbox revisions permit unchanged assets to be reused.

Runtime routes are evaluated in registration order; preserve the exclusions before the general API strategy:

| Requests | Strategy and limits |
| --- | --- |
| Same-origin `/api/auth/*`, both notification/audit heartbeat endpoints, `/api/version`, `/manifest.webmanifest`, `/api/files/download*`, API paths ending `/download` or `/export`, report export patterns | NetworkOnly; never serve cached responses |
| Other same-origin GET `/api/**` | NetworkFirst, 5-second fallback timeout, status 200 only; `api-<version>`; 500 entries, 24-hour maximum age |
| Navigations | NetworkFirst, 4-second fallback timeout, status 200 only; `pages-<version>` |
| Same-origin images, fonts and `/storage/*`, after earlier matches | StaleWhileRevalidate; `assets-<version>`; status 200 only, 200 entries, 30-day maximum age |

The precache route takes precedence for its exact URLs. API caching covers GET responses regardless of whether an endpoint is public or authenticated. The navigation rule has no explicit same-origin predicate; it uses the worker's controlled navigation requests. Cache expiration is lazy Workbox eviction, not a scheduled exact-time deletion.

When navigation cannot use the network or an existing cached page, the catch handler returns the precached offline HTML if available. There is no offline response fallback for arbitrary failed API calls. A device that has never successfully installed/cached the app cannot bootstrap it from nothing while offline. APIs not previously loaded and files intentionally excluded from caching still need connectivity.

During activation, runtime caches from other versions are deleted. The implementation keeps the current API/pages/assets cache names and names beginning `workbox-precache`; other names on this origin are removed. Workbox manages precache revision cleanup separately. Host the app on a dedicated origin; do not add unrelated origin cache users without reviewing this cleanup.

## Authentication and offline writes

Cache names contain the release, not a user ID. Isolation is implemented through `clearAuthTokens()` -> `purgeApiCache()`, called on login before new tokens are stored, logout and session clearing after authentication failure. The purge deletes all `api-*` caches directly and sends `PURGE_API_CACHE` to the controller as an additional cleanup path. Keep this single choke point rather than duplicating account-switch logic in pages.

The application also purges API data after an accepted update and on startup when the saved bundle identity differs from the current identity. Simply detecting a server mismatch no longer destroys offline data while the user is still working.

API mutations are not put in an offline queue. When an API request fails and the browser reports offline, the shared API layer uses the translated `pwa.offline_action_blocked` error. This is failure-time handling, not a guarantee that `navigator.onLine` can detect every network outage. Cached data does not grant server permissions; mutations and uncached requests still require a reachable authorized server.

Notification/audit heartbeats clear server delivery flags, so caching them would break delivery. Both remain NetworkOnly. The notification sound has one served source at `/sounds/notification-ding.mp3` and is explicitly precached; no repository-root duplicate is served.

## Manifest, branding and icons

`/manifest.webmanifest` is a public Laravel route, not a static file. It returns `application/manifest+json` with `public, max-age=300`. Worker NetworkOnly avoids Cache Storage for it; browser/CDN caching can still respect those five minutes.

- Name/short name: Settings `short_company_name`, then `website_title`, then `Educational Agency Portal`.
- Theme: `primary_color`, default `#7367F0`; background: `light_background_color`, default white.
- Start URL/scope: `/`; display: `standalone`; orientation: `any`.
- Uploaded `website_logo`, when present, is the first icon entry. MIME is selected by file extension. Static fallback entries are `/icon-192.png`, `/icon-512.png` and `/maskable-512.png`.
- The Blade shell uses the uploaded logo as favicon or `/favicon.svg`, links `/apple-touch-icon.png`, and uses company title/theme settings.
- `npm run pwa:assets` generates the static icon set from `public/favicon.svg`, using `pwa-assets.config.ts`. Current output is at the **public root**, not `public/icons/`: `icon-64.png`, `icon-192.png`, `icon-512.png`, `maskable-512.png`, `apple-touch-icon.png` and `favicon.ico`.

Dynamic branding needs no frontend build to change the manifest response. Browser favicon and installed-app icon/name refresh policies are separate from JavaScript updates; installed branding may take time or require reinstalling. Static generated icon changes require generation and a production build so precache revisions are refreshed. Do not promise an immediate installed-icon change after accepting the app update toast.

## Manual deployment contract

The owner deploys manually. `.github/workflows/deploy.yaml` is stale and is not the deployment procedure; do not enable it as part of PWA maintenance.

1. Choose the intended source release. Either deploy its complete committed generated artifacts or build that source in a compatible Node/dependency environment. For a new code change, run `npm run test:pwa-updates`, `npm run typecheck`, and the applicable PHP checks before `npm run build`. Use the pinned package manager and lockfile for dependency installation.
2. If favicon source changed, run `npm run pwa:assets` before the production build. Never hand-edit generated icons, `public/build`, `public/sw.js` or `public/version.json`.
3. Publish the full matching release, preserving `.env`, uploaded files and storage. Prefer staging/atomic activation so new metadata never advertises assets still being copied. Keep prior hashed assets available during a transition when old tabs may still request lazy chunks.
4. Continue the deployment's normal Composer, migrations, Laravel optimization and worker restart steps as appropriate. Those commands do not substitute for the frontend build.
5. Point the document root at Laravel's `public` directory when hosting allows it. If hosting uses a project-root rewrite into `public`, preserve `public/.htaccess`, forward every PWA path, and block private project files by explicit paths/names. Do not globally deny JSON or webmanifest files.
6. Serve `/sw.js` as JavaScript at the origin root, without login redirects or HTML fallback. It must not return 403. `public/.htaccess` sets no-cache/no-store for `sw.js` and `version.json` when Apache `mod_headers` is available; configure equivalent headers on Nginx/CDNs. `/api/version` independently sends no-store.
7. Verify `/api/version`, `/sw.js`, `/manifest.webmanifest`, fallback icons, `/offline.html`, the chime and referenced `/build/assets/*` return the intended content successfully over HTTPS. Confirm metadata, bundle and worker come from the same build. Do not expose storage secrets or project metadata to make these URLs work.

Vite dev mode intentionally disables service workers. A development server is insufficient for production-worker acceptance testing.

## Diagnosis and regression procedure

| Symptom | Check |
| --- | --- |
| No update toast | Network response from `/api/version`; visible/online poll; worker's installing/waiting state; matching worker identity; console `[PWA]` messages; failed precache asset requests |
| Repeated prompt without a new intended release | Compare all three build identities, not deployed Git HEAD; look for mixed artifacts, timestamp-fallback builds, unnecessary rebuilds on another SHA, multiple server nodes serving inconsistent versions or an old client still running the earlier update logic |
| Click leaves app unchanged | Activation error toast/console; new worker controller and state; asset availability; blocked `/sw.js`; wait for a valid ready worker rather than repeatedly forcing reload |
| Manifest or worker 403 | Hosting permission/security/error logs and root/public rewrites; manifest is a Laravel route, while sw.js is a generated public file |
| Old logo after update | Manifest's five-minute HTTP TTL, image cache, installed-app/browser icon policy and whether static icons were regenerated |
| Old data after account change | Confirm all identity changes pass through clearAuthTokens and no producer bypasses purgeApiCache |

Inspect DevTools Application -> Service Workers/Cache Storage and Network. `fetch('/api/version', { cache: 'no-store' }).then(r => r.json())` shows the current server payload; `navigator.serviceWorker.getRegistration('/')` exposes active/installing/waiting workers. Avoid using DevTools "Update on reload" or clearing/unregistering workers as the normal acceptance flow: those can mask lifecycle bugs and discard offline data.

For each meaningful update change:

1. Run `npm run test:pwa-updates` (worker identity/readiness, unchanged version, first install, slow activation, errors/retries, concurrent checks, rollback, other tabs, real worker messages, version contract/polling).
2. Run `php artisan test --filter=PwaManifestTest`, `npm run typecheck`, `npm run i18n:check` and `npm run build` in the supported dependency environment. Retain `tsconfig.json` noEmit; never run bare tsc/vue-tsc.
3. In a real browser over HTTPS/localhost, install build A, open two tabs and leave unsaved form input. Deploy B atomically. After visibility return or the polling interval, wait for installation and verify one ready toast per old tab. Click in one tab: it reloads once into B; the other tab retains input and accepts separately.
4. Repeat with the same release, a slow install, a failed asset download, first install, activation timeout, offline mode, user switching and a rollback. Same-release/failed installs must not create update prompts. Confirm print output and heartbeat delivery remain correct.

These browser steps have not been executed for this task in the current environment. Existing clients initially running the previous plugin can still follow its earlier prompt/auto-reload behavior during the first upgrade. The stricter per-tab consent and verification apply after the updated frontend is loaded; server deployment cannot retroactively replace code already executing in a tab.

Lifecycle background: [service-worker skipWaiting](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/skipWaiting), [clients.claim](https://developer.mozilla.org/en-US/docs/Web/API/Clients/claim). These platform references do not override the application's explicit readiness/consent rules above.
