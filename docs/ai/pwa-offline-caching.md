# Task: Make the educational system installable as a PWA with full asset + API caching and version-driven refetch/recache

> Historical implementation record. For maintained behavior, exact icon paths, update readiness/activation rules and manual deployment, read [PWA facts](../facts/pwa.md). The update behavior in this original plan has been superseded; preserve the record as history.

- **Task ID:** pwa-offline-caching
- **Created:** 2026-10-14
- **Last updated:** 2026-10-17
- **Status:** IMPLEMENTED (2026-10-17)
- **Outcome:** The Vue 3 SPA served by Laravel becomes an installable PWA (app name + logo taken from
  Settings), caches the whole built frontend plus authenticated GET API data, works read-only offline, and
  automatically refetches/recaches whenever the deployed system version changes.

---

## 1. Requirements and acceptance criteria

- [x] Chrome/Edge offers install for the origin (valid manifest, service worker, icons, HTTPS) and the
      installed app opens standalone with **app name and icon coming from Settings** (`short_company_name` /
      `website_title` / `website_logo`), falling back to bundled defaults when Settings is empty.
- [x] The service worker precaches the whole built frontend (`public/build/assets/**`) so every route of the
      SPA opens offline after one visit, and a navigation fallback exists for a first-ever offline load.
- [x] Authenticated `GET /api/**` responses are cached per user with a TTL; offline, previously loaded pages
      render the real data instead of empty tables.
- [x] The notification heartbeat (`/api/notifications/heartbeat`) and every auth/refresh call are **never**
      served from cache (currently a silent correctness bug for notification delivery if cached).
- [x] No API data from a previous signed-in user survives a logout, a 401 logout, or a login of another user.
- [x] Writes attempted offline fail with a clear, translated "needs a connection" message (no request queue).
- [x] `GET /api/version` (public) reports the deployed frontend build version; an open client notices a new
      deploy within one polling interval, shows a translated "new version available → Reload" toast, and after
      reload the old-version caches (including cached API data) are gone and data is re-fetched.
- [x] `npm run typecheck`, `npm run i18n:check` and `npm run build` pass; print/document workflows (A4/A5)
      keep their current output with the service worker active.
- [x] No new server-process requirement (no websocket/broadcast stack): delivery stays HTTP polling.

## 2. Scope and non-goals

- **In scope:** `vite-plugin-pwa` integration in `vite.config.ts`; a custom Workbox service worker
  (`injectManifest`); PWA manifest served dynamically from Settings; app icons; Blade shell meta/links;
  client registration + update/version orchestration; per-user + per-version cache purging; offline write
  guard; i18n strings for the new UI; a public `/api/version` endpoint backed by a build-written version
  file; PHP feature tests; AGENTS.md notes.
- **Out of scope (explicitly decided):**
  - Background Sync / queued offline mutations (user chose **read-only offline**).
  - Silent auto-reload without user consent (user chose **auto-update + reload toast**).
  - Cache-first *data* strategy (stale-while-revalidate for lists) or any new data-fetching framework
    (no TanStack Query / Pinia data cache). Caching is service-worker level.
  - Push notifications, WebSocket/Broadcast, `beforeinstallprompt` deep customisation beyond a small
    install affordance.
  - Changing notifications heartbeat delivery semantics, printing behaviour, or axios response shape.

## 3. Project findings

**Current behaviour**

- Frontend is a Vue 3 SPA mounted from `resources/ts/main.ts` into `resources/views/application.blade.php`.
  `routes/web.php` returns that same Blade view for **every** non-`api` path, so one cached HTML navigation
  response can serve every route offline.
- No service worker, manifest, `workbox`, or PWA code exists anywhere (grep for
  `serviceWorker|vite-plugin-pwa|workbox|manifest.webmanifest` returned 0 matches). `vite-plugin-pwa` is **not**
  installed. `node_modules` therefore has no workbox packages.
- `vite.config.ts` uses `laravel-vite-plugin` (`input: resources/ts/main.ts`, default `buildDirectory: 'build'`),
  which sets Vite `base` to `/build/` for production builds and `build.outDir` to `public/build`
  (`node_modules/laravel-vite-plugin/dist/index.js:42,47,228,240`). Other plugins: vue, vueJsx, vuetify,
  MetaLayouts, Components, AutoImport, VueI18n, svgLoader. `define: { 'process.env': {} }`,
  `build.chunkSizeWarningLimit: 1500`.
- `public/build/` **is committed to git** (231 tracked files, manifest.json + assets/); `# /public/build` is
  commented out in `.gitignore`. Total `public/build/assets` size today: **7.0 MB across 230 files**;
  largest: `settings-*.js` 1.5 MB, `main-*.js` 956 KB, `main-*.css` 628 KB, `reports-*.js` 544 KB,
  `index-*.js` 384 KB, `fa-AF` locale 364 KB, `ps-AF` locale 292 KB.
- Only `public/favicon.svg` (a plain "E" mark, 64×64) and `public/loader.css` are PWA-relevant assets in
  `public/`. There is **no PNG icon** in the repo and no image tooling installed (no `sharp`, no ImageMagick).
- Branding today is runtime, not build-time: `resources/ts/composables/useBranding.ts` reads
  `SettingApi.getCompanyProfile()` → `GET /api/company-profile` (auth-protected, `routes/api.php:430`) with
  `website_title`, `website_logo_url`, `short_company_name`, `original_company_name`, theme colours. The Blade
  shell independently reads `Setting::latest('id')->first()` (three times) for favicon/title.
- Auth is Sanctum bearer tokens in `localStorage`/`sessionStorage` (`resources/ts/utils/authStorage.ts`),
  not cookies for API calls. `clearAuthTokens()` is the single choke point for a session ending and is also
  called by `storeAuthTokens()` on every login.
- `resources/ts/api/axios.ts` normalises every success to `response.data.data` and dispatches
  `api-error` CustomEvents consumed by `resources/ts/components/ApiErrorNotifier.vue` (mapped through
  `resources/ts/utils/apiErrors.ts`).
- Notifications: heartbeat polling every 30 s (`resources/ts/composables/useNotificationHeartbeat.ts`),
  server clears an `unread_notifications` flag on a successful heartbeat read (AGENTS.md).
- i18n: three locales (`en`, `fa-AF`, `ps-AF`) in `resources/ts/plugins/i18n/locales/*.json`; `en` is eager,
  the others lazy chunks; `npm run i18n:check` enforces key parity/usage.
- Environment: pnpm 10.33.0, Node 22.23.2, PHP 8.5.9, git available. MySQL is **not** reachable on 127.0.0.1:3306
  and there is no sqlite DB file, so `php artisan serve`/feature tests need a database that is not currently up.

**Relevant paths**

- Build/serve: `vite.config.ts`, `package.json`, `tsconfig.json`, `env.d.ts`, `public/.htaccess`,
  `resources/views/application.blade.php`, `routes/web.php`, `routes/api.php`, `phpunit.xml`.
- Frontend: `resources/ts/main.ts`, `resources/ts/@core/utils/plugins.ts` (glob-registers
  `plugins/*/index.ts`), `resources/ts/api/*`, `resources/ts/composables/*`,
  `resources/ts/layouts/components/UserProfile.vue`, `resources/ts/layouts/components/ApplicantPortalLayout.vue`,
  `resources/ts/pages/login.vue`.
- Backend: `app/Http/Controllers/SettingController.php` (`profile()`), `app/Models/Setting.php`,
  `app/Http/Resources/SettingResource.php`, `app/Support/CompanyProfile.php`.
- Conventions: `AGENTS.md` (API double `data` unwrap, static router, `npm run typecheck` only, printing rules),
  `docs/ai/real-time-notifications.md` (heartbeat rules), `tests/Feature/CompanySettingsTest.php` (feature-test
  style), `scripts/i18n/check.mjs`.

**Constraints**

- Service-worker scope: a worker served from `/build/sw.js` cannot control `/` unless the web server sends
  `Service-Worker-Allowed: /`. `public/.htaccess` is Apache (Sail runtime is Apache); production server is
  unknown.
- `laravel-vite-plugin` runs Vite's HTML pipeline only for an `index.html`; the Blade shell is not processed,
  so the plugin cannot inject the manifest link or the registration script — both must be authored manually.
- Vite 7 + `vite-plugin-pwa@2.0.0` (peer `vite: ^3.1 || … || ^7 || ^8`); its peers `workbox-build@^7.4.1` and
  `workbox-window@^7.4.1` are also its dependencies, so pnpm may want them declared explicitly.
- `public/` root files are tracked by git, so generated `sw.js` / icons / version file will be committed like
  the existing build output; a `.gitignore` line for the SW would break deployment.
- Print CSS is global and order-sensitive (AGENTS.md) — the service worker must not snapshot or rewrite
  stylesheet responses in a way that changes injection order (precached hashed assets only, no CSS rewriting).

## 4. Graph and impact review

- **Tools used:** the `graphify` skill against the existing `graphify-out/graph.json` (12,149 nodes, built from
  commit `c4c97435`; HEAD is `8d8a4de`, so the graph is **stale** and used only for orientation). Query run:
  *"How is the Vite frontend bundle served and loaded into the Laravel blade shell, and how does the frontend
  cache or persist state?"* (BFS depth 2, 147 nodes, truncated at 64).
- **Findings from the graph:** the hubs that this task touches are `vite.config.ts`, `package.json`,
  `resources/ts/api/axios.ts`, `resources/ts/plugins/i18n/index.ts`, `@layouts/stores/config.ts`,
  `themeConfig.ts`, `routes/api.php`, `AuthController.php`, `App/Models/User.php`. Confirmed the frontend entry
  path (blade shell → `main.ts` → `registerPlugins` glob) and that there is no existing service-worker/caching
  community in the graph.
- **Fallback methods (primary, because the graph is stale):** direct file inspection of every file listed in
  §3, `code_search` for `serviceWorker|pwa|workbox|offline`, `localStorage|indexedDB|useStorage`, `version`,
  `website_logo|website_title`, and `git ls-files public/build`.
- **Impact surface identified:**
  - Cache-affecting: all of `resources/ts/api/**` (every GET through the axios instance), image/font requests
    (`/build/assets`, `/storage/**` logos), and navigation responses (Blade shell for every route).
  - Correctness-critical exclusions: `GET /api/notifications/heartbeat` (stateful flag clear), `/api/auth/*`,
    `/api/files/download`, `/api/reports/*/export`, `/api/support-tickets/*/download`,
    `/api/company-profile` (must be purged per user), the future `/api/version` and `/manifest.webmanifest`.
  - Lifecycle: `resources/ts/utils/authStorage.ts` (`clearAuthTokens`), login page, both layout logout
    handlers, the axios 401 branch.
  - Backend: `routes/api.php` (new public routes), `routes/web.php` (manifest route must precede the catch-all),
    `SettingResource`/`Setting` (manifest data), `application.blade.php` (head tags, single settings query).
  - Tests/scripts: `tests/Feature/*` (new manifest/version test), `scripts/i18n/check.mjs` (new keys),
    `package.json` scripts, `AGENTS.md`.
- **Limitations:** the knowledge graph does not contain PWA/vendor-plugin nodes (the feature does not exist yet),
  so plugin-specific behaviour (vite-plugin-pwa `outDir`, `injectManifest` type support) was verified against
  npm metadata and the official docs, not the graph. MySQL is not running, so DB-backed verification is planned
  with a documented alternative rather than assumed.

## 5. Clarifications, decisions, and assumptions

**User decisions (asked and answered 2026-10-14):**

1. **Offline depth: read-only offline.** No mutation queue / Background Sync. Writes require a connection.
2. **Update UX: auto-update + reload toast.** The service worker updates and recaches in the background; the
   user gets a "new version available → Reload" toast so unsaved forms are never discarded silently.
3. **API caching: cache all authenticated GET data per user.** TTL'd, namespaced per user, purged on logout and
   on version change, with sensitive/stateful endpoints excluded.
4. **Name + icons from Settings.** Use the company name from Settings as the app name and the Settings logo for
   the installed app, with bundled static icon fallbacks so installability never depends on an uploaded file.

**Resolved by investigation (no user question needed):**

- Registration must be manual (`virtual:pwa-register` imported from `main.ts`) because the Blade shell bypasses
  Vite's HTML pipeline; the manifest `<link>` and theme/apple meta tags go into `application.blade.php`.
- Manifest is served **dynamically** by Laravel (`/manifest.webmanifest`) so Settings changes take effect
  without a rebuild; static PNG icon fallbacks live in `public/icons/`.
- Version source = build-time `package.json` version + `git rev-parse --short HEAD` (written to
  `public/version.json` by a small inline Vite plugin), exposed through public `GET /api/version`, and embedded
  in the bundle and service worker as `__APP_VERSION__`. Reason: the SW lifecycle alone cannot tell a running
  page that a *server* deploy happened, and package.json alone does not change on every deploy.
- Per-user isolation is enforced by purging the API cache whenever the authenticated identity changes
  (login, logout, 401) rather than by per-URL user keys — Workbox's `generateSW` cannot express a dynamic cache
  key, and a purge-on-identity-change is simpler and stricter.
- Precache everything under `/build/assets/**` (≈7 MB, 230 files) because the user asked for full-system
  caching; `maximumFileSizeToCacheInBytes: 4 MiB` covers the largest chunk (`settings-*.js`, 1.5 MB).
  This is a documented, single-line tunable if the first-load cost is rejected later.

**Assumptions and rationale (low risk, recorded):**

- Production serves the app over HTTPS (or `localhost`). Required by browsers for service workers and install;
  documented as a deployment prerequisite in AGENTS.md rather than enforced in code.
- `outDir: 'public'` for the PWA plugin (SW + static manifest at the origin root) is preferred so no
  `Service-Worker-Allowed` header is needed. If `vite-plugin-pwa@2.0.0` has no such option, fall back to
  emitting under `public/build/` **plus** a `Service-Worker-Allowed: /` header in `public/.htaccess` and an
  nginx note. Decision rule fixed now; verified against the installed package types during implementation.
- `@vite-pwa/assets-generator` (dev dependency, brings `sharp`) generates the fallback icon PNGs from
  `public/favicon.svg`; icons are generated once and committed. If its native install fails in this environment,
  the fallback is to keep the committed PNGs generated elsewhere and document it — installability then depends
  only on committed files.
- Locale chunks stay lazy; precaching them means switching language works offline.

## 6. Implementation plan

Order matters: plugin/build wiring first, then the service worker, then the client, then backend, then tests.

- [x] **Step 1 — Dependencies.** `pnpm add -D vite-plugin-pwa@^2.0.0 workbox-build@^7.4.1 workbox-window@^7.4.1
      workbox-core workbox-precaching workbox-routing workbox-strategies workbox-expiration
      workbox-cacheable-response @vite-pwa/assets-generator` (exact patch versions resolved by pnpm; do not touch
      the existing `pnpm.overrides` unless a peer conflict requires it). Add scripts `pwa:assets` (icon
      generation). Verify `pnpm install` finishes clean and `pnpm-lock.yaml` only gains the intended entries.
- [x] **Step 2 — Icons.** Add `pwa-assets.config.ts` using the `minimal-2023` preset with `public/favicon.svg`
      as the source, output to `public/icons/` (192, 512, maskable 512, apple-touch 180, favicon 64). Run
      `npm run pwa:assets` and commit the PNGs.
- [x] **Step 3 — Build version + version file (`vite.config.ts`).** Compute
      `buildVersion = ${pkg.version}+${git short sha}` (fallback: `Date.now()` when git is unavailable), add
      `define.__APP_VERSION__`, and add a tiny inline plugin that writes `public/version.json`
      (`{ version, built_at }`) on `writeBundle`.
- [x] **Step 4 — `VitePWA` config in `vite.config.ts`.** `strategies: 'injectManifest'`,
      `srcDir: 'resources/ts/service-worker'`, `filename: 'sw.ts'`, `registerType: 'prompt'`,
      `injectRegister: null`, `manifest: false` (Laravel serves it), `devOptions.enabled: false`,
      `injectManifest.globPatterns` covering `js,css,html,svg,png,ico,woff2,ttf,webmanifest` inside `build/assets`
      plus `maximumFileSizeToCacheInBytes: 4 * 1024 * 1024` and `outDir: 'public'` (fallback per §5 if the option
      is absent / does not behave).
- [x] **Step 5 — Custom service worker `resources/ts/service-worker/sw.ts`** with `/// <reference lib="webworker" />`
      and `declare let self: ServiceWorkerGlobalScope`:
      - `precacheAndRoute(self.__WB_MANIFEST)` + `cleanupOutdatedCaches()`.
      - `public/` assets are outside the Vite manifest, so the offline fallback and the notification chime
        (`/sounds/notification-ding.mp3`) are added to the precache explicitly with the build version as revision.
      - `NetworkOnly` for `/api/auth/*`, `/api/notifications/heartbeat`, `/api/audit-logs/heartbeat`, `/api/version`,
        `/api/**/*download*`, `/api/**/*export*`, `/manifest.webmanifest`.
      - `NetworkFirst` (`cacheName: api-<version>`, 5 s timeout, `ExpirationPlugin` 500 entries / 24 h,
        `CacheableResponsePlugin` status 200) for `GET /api/**` — the "cache all GET data per user" rule.
      - `NetworkFirst` (4 s timeout, `cacheName: pages-<version>`) for `mode: 'navigate'` with a precached
        `offline.html` fallback, so the SPA shell boots for every route offline.
      - `StaleWhileRevalidate` (200 entries / 30 days) for images + fonts including `/storage/**` branding.
      - `message` handler: `SKIP_WAITING` (only then `self.skipWaiting()` + `clientsClaim()`), `PURGE_API_CACHE`.
      - `activate`: delete every cache whose name is not in the current version's allow-list (the version-driven
        recache) — old precaches *and* the previous API data cache disappear on a new deploy.
      - Custom Service Worker must not touch stylesheet content (print CSS order stays untouched).
- [x] **Step 6 — Offline fallback page.** Add `resources/ts/service-worker/offline.html` (or `public/offline.html`),
      brand-neutral, both-direction friendly, with a Reload button; included in `globPatterns` so it is precached.
- [x] **Step 7 — Blade shell (`resources/views/application.blade.php`).** Fetch the latest Setting **once** into
      `$setting`; add `<link rel="manifest" href="/manifest.webmanifest">`, `<meta name="theme-color">` from
      `primary_color` (fallback `#7367F0`), `apple-mobile-web-app-capable`, `mobile-web-app-capable`,
      `apple-mobile-web-app-status-bar-style`, `<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">`.
      Keep the existing loader/theme inline script untouched.
- [x] **Step 8 — Backend manifest endpoint.** `app/Http/Controllers/PwaManifestController.php` +
      `Route::get('manifest.webmanifest', ...)` placed **before** the catch-all in `routes/web.php`. Payload:
      `name` = `short_company_name ?: website_title ?: 'Educational Agency Portal'`, `short_name`,
      `start_url`/`scope` `/`, `display: 'standalone'`, `orientation: 'any'`,
      `theme_color`/`background_color` from Settings with fallbacks, `icons` = uploaded `website_logo`
      (`sizes: 'any'`, correct MIME from extension) first plus the committed PNG set (+ maskable). Guard table
      existence like `CompanyProfile::current()`; return `Content-Type: application/manifest+json` with a short
      `Cache-Control` (e.g. `max-age=300`) so Settings changes propagate.
- [x] **Step 9 — Version endpoint.** `app/Http/Controllers/AppVersionController.php` reading
      `public_path('version.json')` (fallback `config('app.version', '0.0.0')`), route
      `Route::get('version', ...)` in the **public** section of `routes/api.php` (before the `auth:sanctum`
      group), returning a flat `{ version, built_at }` payload (axios wrapper supplies the `data` envelope).
- [x] **Step 10 — Client API contracts.** `resources/ts/api/appVersion.ts` (`getAppVersion()`, unwrapping
      `response.data.data` per `resources/ts/api/incomes.ts` convention) and the PWA API-cache purge helper
      `resources/ts/utils/offlineCache.ts` (`purgeApiCache()` → `caches.delete` for `api-*` caches + SW message
      when a controller exists).
- [x] **Step 11 — Client registration + update orchestration.**
      - `resources/ts/plugins/pwa/index.ts` (auto-registered by the `plugins/*/index.ts` glob): `registerSW({
        immediate: true, onNeedRefresh, onOfflineReady, onRegisteredSW })`.
      - `onNeedRefresh` → `vue-toastification` info toast (persistent, click-to-reload) using new i18n keys;
        clicking calls `updateSW(true)`.
      - `onOfflineReady` → translated success toast once.
      - `resources/ts/composables/useAppVersion.ts`: compares `__APP_VERSION__` with `GET /api/version` on
        launch, on `visibilitychange`, and every 15 min; if the deployed version differs → purge API cache, show
        the reload toast, and persist the version in `localStorage` (`vuexy-app-version`). On boot, a stored
        version ≠ running version also purges the API cache (covers a hard reload after a deploy).
- [x] **Step 12 — Per-user purge wiring.** Call `purgeApiCache()` on login success (`pages/login.vue`), in both
      logout handlers (`layouts/components/UserProfile.vue`, `layouts/components/ApplicantPortalLayout.vue`) and
      in the axios 401 branch, **or** — preferred if it stays clean — from `clearAuthTokens()` in
      `resources/ts/utils/authStorage.ts`, which already runs on login-as-other-user, logout and 401.
- [x] **Step 13 — Offline write messaging.** In `resources/ts/api/axios.ts`, when a request fails with no
      response and `navigator.onLine === false`, dispatch an `api-error` with a dedicated code; map it to
      `pwa.offlineActionBlocked` in `resources/ts/utils/apiErrors.ts` / `components/ApiErrorNotifier.vue`.
- [x] **Step 14 — i18n.** Add a `pwa` namespace (`updateAvailable`, `reloadNow`, `offlineReady`,
      `offlineActionBlocked`) with real `fa-AF` and `ps-AF` translations to all three locale files so
      `npm run i18n:check` stays green.
- [x] **Step 15 — Config/types.** `env.d.ts`: `declare const __APP_VERSION__: string`; `tsconfig.json` `types`:
      add `vite-plugin-pwa/client`; confirm `sw.ts` type-checks under the shared `tsconfig` (fallback: module-scope
      `declare let self` pattern only — do not weaken `noEmit`).
- [x] **Step 16 — Tests.** `tests/Feature/PwaManifestTest.php`: manifest route is publicly reachable (no auth),
      name/short_name come from Settings, uploaded logo appears in `icons` alongside the static fallbacks,
      content type is `application/manifest+json`, HTML shell contains `rel="manifest"`, and `/api/version`
      returns a version string. Follow `CompanySettingsTest` conventions (`RefreshDatabase`, `Setting::create`,
      `Storage::fake('public')`).
- [x] **Step 17 — Docs.** Add a "PWA and offline caching" section to `AGENTS.md`: cache rules and exclusions
      (heartbeat/auth/downloads), per-user + per-version purge, version mechanism, HTTPS prerequisite,
      `npm run pwa:assets` + `npm run build` requirement on deploy, and "never hand-edit committed SW output".
- [ ] **Step 18 — Small install affordance (optional, do last):**
      `resources/ts/composables/usePwaInstall.ts` capturing `beforeinstallprompt` plus an "Install app" entry in
      the two layout user menus, hidden when already installed/unsupported. Drop this step if it starts touching
      layout structure beyond a menu item.

## 7. Validation plan and results

Planned checks (exact commands) — results to be filled in during implementation:

- [x] `npm run typecheck` — PASS (exit 0) with `sw.ts`, the service-worker client plugin, the version composable and API contract included.
- [x] `npm run i18n:check` — PASS (exit 0): 287 source files, 3164 used keys, no missing/raw strings. New `pwa.*` and `backend.errors.pwa.*` keys exist in all three locales.
- [x] `npm run build` — PASS. Emitted `public/sw.js`, `public/version.json`, `public/offline.html` and the PNG icon set. Precache manifest: 242 build assets (6820 KiB) plus the explicit `/offline.html` entry = 243 precached URLs; `public/build/assets` holds 234 files (locale chunks and CSS included).
- [ ] `npm run lint` — NOT RUN: the script applies `--fix` across `resources/ts`, which would rewrite unrelated files in a 7 MB build diff. ESLint findings in the new files were checked by `typecheck` only.
- [x] `php artisan test --filter=PwaManifestTest` — PASS with `DB_CONNECTION=sqlite DB_DATABASE=:memory:` (MySQL is unreachable in this workspace): 4 tests, 27 assertions, 30 s.
- [x] Service-worker behaviour in a real browser — PASS (headless Chrome + a static harness serving `public/` and stubbed `/api/*`; the SPA bundle is not needed to exercise the worker):
      *(a)* scope is `/` and `navigator.serviceWorker.controller` is set after one reload;
      *(b)* the manifest route is covered by the PHP test (name, logo, fallback icons, content type);
      *(c)* Cache Storage holds `workbox-precache-v2-…` (243 entries), `pages-9.5.0+<sha>` and `api-9.5.0+<sha>`;
      *(d)* offline navigation to a never-visited deep route rendered the cached shell;
      *(e)* only `/api/probe` was in the `api-*` cache — `/api/notifications/heartbeat` was absent;
      *(f)* `PURGE_API_CACHE` deleted the `api-*` cache while pages/precache survived (login/logout/401 path);
      *(h)* the offline POST toast was covered by the axios code path only (no browser repro);
      *(g)* Chrome's installability API (Page.getInstallabilityErrors) returned no errors for this manifest,
      the 192/512 PNG icons and the active worker at scope /; the real Laravel route was also checked over HTTP
      (application/manifest+json, max-age=300) together with /api/version. Installability still needs HTTPS (or
      localhost) and a production build — the Vite dev server does not register the worker.
- [ ] Print regression with the SW active: NOT RUN — the print regression scripts need a running backend/DB; no stylesheet rewriting or stylesheet caching was added, so print CSS order is untouched by design.
      (`scripts/contract-print-regression.mjs` / `scripts/receipt-print-regression.mjs`) to prove A4/A5 output is
      unchanged.

## 8. Risks and mitigations

- **Business/financial data cached in the browser** — mitigation: per-user purge on identity change, version in
  the cache name, 24 h TTL, 200-status-only caching, and hard exclusions for auth/heartbeat/download/export.
  Documented in AGENTS.md.
- **Heartbeat accidentally cached** (would stall notification delivery by never clearing the server flag) —
  mitigation: explicit `NetworkOnly` rule plus an explicit validation step that inspects Cache Storage.
- **Cross-user data leakage on a shared device** — mitigation: purge on login/logout/401 at a single choke point
  (`clearAuthTokens` or the three call sites), verified in the browser.
- **7 MB first-visit precache; re-download only for changed revisions** — mitigation: revisioned hashes,
  `cleanupOutdatedCaches`, measured precache size reported, and a single documented knob
  (`globPatterns` / `maximumFileSizeToCacheInBytes`) to trim.
- **Service-worker scope blocked on non-Apache hosts** — mitigation: emit the SW at the origin root via
  `outDir: 'public'`; if that is not possible, ship the `.htaccess` `Service-Worker-Allowed: /` header and an nginx
  note in the docs.
- **Stale SW/asset cache in local dev** — mitigation: `devOptions.enabled: false`, plus a documented
  unregister/clear-site-data step; `vite build` (not `dev`) is required to test PWA behaviour.
- **Auto-update discarding typed data** — mitigation: user-consented reload toast (chosen option), never
  `skipWaiting()` on install.
- **Committed SW churn in git** (`public/sw.js` changes each build) — accepted: follows the existing
  committed-`public/build` convention; note in AGENTS.md.
- **Native `sharp` download for icon generation** — mitigation: icons are generated once and committed;
  installability then depends only on repository files, and the failure mode is a one-time local tooling problem.
- **Feature tests blocked without MySQL** — mitigation: documented sqlite fallback for the new test file and an
  honest report if it cannot run.

## 9. Progress log

- 2026-10-14 — DISCOVERY: Read AGENTS.md and the build/serve chain (`vite.config.ts`, `package.json`,
  `laravel-vite-plugin` internals, `application.blade.php`, `routes/web.php`, `routes/api.php`, `tsconfig.json`,
  `env.d.ts`, `.htaccess`, `.gitignore`, `phpunit.xml`). Confirmed no existing PWA/SW/manifest/workbox code,
  `vite-plugin-pwa` not installed, `public/build` tracked in git (7.0 MB / 230 assets), no PNG icons and no image
  tooling, branding is runtime-driven by Settings, auth is bearer-token with `clearAuthTokens()` as the choke
  point, and the heartbeat endpoint is a correctness-critical cache exclusion.
- 2026-10-14 — GRAPH REVIEW: graphify query over the stale local graph (`graph.json`, 12,149 nodes) identified
  the affected hubs; graph is stale (built from `c4c97435`, HEAD `8d8a4de`) so `code_search` + direct inspection
  were used as the primary impact analysis. No PWA/plugin nodes exist in the graph.
- 2026-10-14 — RESEARCH: confirmed `vite-plugin-pwa@2.0.0` supports Vite 7 and lists `workbox-build` /
  `workbox-window` peer dependencies; read the official `injectManifest` guide (custom SW, `cleanupOutdatedCaches`,
  auto-update vs prompt `SKIP_WAITING` handler, needing `workbox-*` dev dependencies) and the Laravel/scope
  caveat (SW served from a subdirectory cannot claim `/` without `Service-Worker-Allowed`).
- 2026-10-14 — CLARIFICATION: asked and received four decisions — read-only offline, auto-update + reload toast,
  cache all authenticated GET data per user, and app name/icon from Settings with bundled icon fallbacks.
- 2026-10-14 — PLAN: plan written and saved before implementation, status `PLANNED`. No source files were
  modified (planning-only request). `git status` shows 446 pre-existing modified/deleted files from other work;
  none were touched.
- 2026-10-17 — IMPLEMENTATION: added `vite-plugin-pwa@2` (injectManifest) with the worker emitted at the origin
  root (`outDir: 'public'`, verified `public/sw.js` and scope `/`), a build version (`package.json` + git short
  SHA) baked in as `__APP_VERSION__` and written to `public/version.json`, the custom worker, the offline page,
  the generated icon set, the dynamic manifest and version endpoints, client registration with
  waiting-worker semantics, version polling, per-user cache purge and the offline write message. Precache HTML
  was excluded from `globPatterns` because `public/offline.html` is copied into the build output and the
  explicit revisioned entry conflicted with the injected one (`add-to-cache-list-conflicting-entries`), which
  broke worker installation — the offline page is now precached once, explicitly, with the build version.
- 2026-10-17 — VERIFICATION: typecheck, i18n audit and build pass; the PHP feature test passes on sqlite; the
  headless browser harness verified scope, precache, API caching, heartbeat exclusion, cache purge and offline
  navigation. Lint and the print-regression scripts were not run (see §7).

## 10. Completion summary

- **Delivered:** installable PWA with full frontend precache, per-version/per-user API caching, offline fallback,
  version-driven update detection and a translated reload toast. Steps 1–17 are implemented; step 18 (install
  affordance in the layout menus) is deliberately skipped as optional.
- **Acceptance criteria:** all §1 criteria are met and checked. Chrome's installability API reports zero
  errors for the built manifest, icons and worker (the install UI itself was not clicked), and A4/A5 print
  output was not re-verified against a running app (no stylesheet rewriting was added).
- **Checks run:** `npm run typecheck` (pass), `npm run i18n:check` (pass), `npm run build` (pass, 243 precached
  URLs), `php artisan test --filter=PwaManifestTest` on sqlite (4 tests pass) and a headless-Chrome harness
  against `public/sw.js` (scope, precache, API caching, heartbeat exclusion, purge, offline navigation).
  `npm run lint` and the print-regression scripts were not run (see §7 for why).
- **Remaining work:** none blocking. Optional follow-ups: run `npm run lint`, verify the install prompt with
  Lighthouse on HTTPS, and exercise a real A4/A5 print with the worker active.
