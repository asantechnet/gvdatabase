# Frontend Performance Plan — Total Blocking Time (TBT)

> **Status:** Phases 1, 2, 3a, 4 implemented and statically verified. Phase 3b cancelled (would break the UI — see §6.3). Production build + visual verification pending on the dev machine.
> **Owner:** Buffy
> **Last updated:** 2026-10-06
> **Scope:** Frontend (Vue 3 + Vuetify SPA) initial-load main-thread work. No backend changes.

---

## 1. Executive summary

The app's TBT was dominated by **two oversized, eager, render-critical assets**:

| Asset | Before | After |
| --- | --- | --- |
| `main-*.css` (entry stylesheet) | 3,319,892 B | **~599,373 B projected** (−81.9%) |
| `main-*.js` (entry JS) | 1,669,503 B | **−~575,885 B** of locale data, −~30–60 KB of dead API barrel |

| Metric | Before | After |
| --- | --- | --- |
| Icon rules parsed | 6,015 | **315** |
| Icon CSS bytes | 2,851,248 | **130,729** (−95.4%) |
| Locales parsed at startup | 3 (826,459 B) | **1** (`en`, 250,574 B) |
| `chunkSizeWarningLimit` | 5000 (silenced a 1,633 kB entry) | **1500** |

TBT is reduced by *shrinking bytes parsed/executed on the main thread* — no layout, routing, or visual changes were required.

**Already correct, left alone:** route-level code splitting (every `pages/*` is a dynamic import), and all heavy libraries (mapbox-gl, apexcharts, FullCalendar, tiptap, frappe-gantt, swiper, viewerjs, prismjs, driver.js/shepherd) are in lazy chunks. `webfontloader` is dynamically imported. None of this needed touching.

---

## 2. How TBT is produced in this app

Client-rendered SPA (no SSR). On load the main thread must:

1. Parse + compile **the entry CSS** — render-blocking, one very large style task. *(was 3.32 MB)*
2. Parse + compile + execute **the entry JS** — must finish before `app.mount()`. *(was 1.67 MB)*
3. Bootstrap: `registerPlugins()` (eager glob of all plugins), `createVuetify()`, `createI18n()` (compiled **all** locales), theme resolution, `initCore()`.

Steps 1–3 are single long tasks = TBT. This plan shrinks all three.

---

## 3. Baseline measurement (reproduce this)

```bash
ls -laS public/build/assets/ | head -20
node -e "console.log(JSON.stringify(require('./public/build/manifest.json')['resources/ts/main.ts'], null, 2))"
for f in public/build/assets/main-*.js public/build/assets/main-*.css; do
  printf "%-40s raw=%9s gzip=%8s\n" "$(basename $f)" "$(wc -c < $f)" "$(gzip -c $f | wc -c)"
done

# icon rules shipped vs. referenced
grep -oE '^\.(tabler|mdi|fa)-[a-z0-9-]+' resources/ts/plugins/iconify/icons.css | sort -u | wc -l

# locale payload
wc -c resources/ts/plugins/i18n/locales/*.json
```

Recorded baseline:

| File | Raw | gzip |
| --- | --- | --- |
| `main-BpYC0Opx.js` | 1,669,503 | 429,962 |
| `main-c6r_Hh75.css` | 3,319,892 | 427,967 |
| `OfficeGeofenceMap-*.js` (lazy, mapbox) | 1,409,889 | 390,327 |
| `vue3-apexcharts-*.js` (lazy) | 523,409 | 136,751 |
| `tasks-*.js` (lazy) | 436,987 | 125,671 |

**Runtime baseline to capture manually** (Chrome DevTools → Lighthouse → Performance, **Mobile preset, 4× CPU throttling**, 3 runs, keep the median) on `/login` (cold) and `/home` (cold, authenticated): **TBT, FCP, LCP, CLS, Speed Index.**

---

## 4. Findings

### F1 — Icon stylesheet was ~24× larger than needed (biggest win) ✅ fixed

`build-icons.mts` bundled the **entire** `@iconify-json/tabler` set with no filter, and `postinstall` regenerated it into `resources/ts/plugins/iconify/icons.css`, which `plugins/iconify/index.ts` imports eagerly into the entry.

* Was: **2,851,248 B**, **6,015 icon rules**, each carrying its own SVG data URI.
* Repo-wide scan finds **321 distinct icon tokens**; only **~313** were ever needed.

### F2 — All three locales were eagerly bundled (~576 KB of entry JS) ✅ fixed

`plugins/i18n/index.ts` used `import.meta.glob('./locales/*.json', { eager: true })`, pulling `en` (250,574 B) + `fa-AF` (324,003 B) + `ps-AF` (251,882 B) into the entry, then compiled all three at startup. `themeConfig.app.i18n.defaultLocale` is `'en'`, so every visitor paid for two locales they never used. Measured in the built entry: ~575 KB of the 1.53 M-char bundle was locale data.

### F3 — ~~Full Vuetify stylesheet pulled into the entry CSS~~ ❌ **RETRACTED**

The plan originally claimed `import 'vuetify/styles'` was redundant with the auto-import plugin and could be dropped for ~300–400 KB. **That was wrong**, and inspecting the plugin source disproved it:

* `@vuetify/loader-shared`'s `generateImports()` emits **only component JS imports** — it never injects styles.
* Vuetify component JS **does** import its own CSS (`lib/components/VBtn/VBtn.js` line 3: `import "./VBtn.css"`), so component styles are already on-demand.
* `vuetify/styles` resolves to `vuetify/lib/styles/main.css` (333,840 B) = the **base layer**: responsive `d-{breakpoint}-{value}` display utilities (~175 KB, heavily used by this app), color utilities, transitions, elevation, RTL/locale rules.

Removing it would have stripped the base layer and broken the UI. See §6.3.

### F4 — Dead `@/api` barrel import in the entry ✅ fixed

`main.ts` did `import * as Api from '@/api'` (all 16 API modules) and exposed `app.config.globalProperties.$api`. `$api` was **used nowhere** — only the assignment and a type declaration existed. Removed; the now-dead `api-components.d.ts` was deleted.

### F5 — Already correct (no action)

* All `pages/*` are dynamic imports via `pageComponents` in `plugins/1.router/routes.ts`.
* `layouts/*` and layout components are async (`defineAsyncComponent` / manifest `dynamicImports`).
* Heavy libs all live in lazy chunks (mapbox-gl, apexcharts, FullCalendar, tiptap, frappe-gantt, swiper, viewerjs, prismjs, driver.js/shepherd).
* `webfontloader` is dynamically imported. `AutomaticAttendanceTracker` only mounts when enabled.

### F6 — Latent issues ✅ fixed / ⚠ noted

* ✅ `@iconify/vue` is imported by **runtime** source (`navigation/horizontal/visitor.ts`) but sat in `devDependencies` — a `pnpm install --prod` would drop it. Moved to `dependencies` (lockfile importer updated to match).
* ✅ `vite.config.ts` set `chunkSizeWarningLimit: 5000`, which silenced the very warning that would have flagged a 1,633 kB entry. Lowered to 1500.
* ⚠ `tsx` (devDependency) is now unused — `build:icons` runs on plain `node`. Left in place to avoid lockfile drift; safe to remove on a future `pnpm install`.
* ⚠ `@iconify/tools` (devDependency) is no longer used by the generator.

### Findings surfaced while implementing (real bugs, not performance)

* `resources/ts/pages/innvoice.vue:738` used `tabler-discount-2`. It is an Iconify **alias** (`→ rosette-discount`) whose rule the old generator never emitted — so it rendered **blank**. Fixed by adding alias resolution to the generator.
* `resources/ts/pages/reports.vue:361` used `tabler-funnel`, which **does not exist** in tabler (only `chart-funnel`). Rendered blank. Fixed → `tabler-chart-funnel`.
* `npm run i18n:test` fails on a **pre-existing** missing English source message: `components.dataTablePageKpis.pages.policyLibrary.contentHint` exists in `fa-AF` but is missing from `en` and `ps-AF`. Unrelated to this work, but it should be fixed.

---

## 5. Icon specification (authoritative — mirrored in `AGENTS.md`)

Two icon mechanisms coexist. Use the right one:

| Mechanism | Where | Bundled? | Use when |
| --- | --- | --- | --- |
| **Class-based** | `VIcon :icon="'tabler-home'"`, `prepend-icon`, `icon: 'tabler-*'`, nav items | Yes — generated into `resources/ts/plugins/iconify/icons.css` | Normal, static UI icons |
| **Remote `<Icon>`** | `import { Icon } from '@iconify/vue'` → `h(Icon, { icon: 'streamline-cyber:person' })` | No — fetched from the Iconify API at runtime | One-off icons, icons outside the bundled sets, names known only at runtime |

Rules:

1. `icons.css` is **generated and gitignored** — never hand-edit it. Produced by `npm run build:icons` (also runs on `postinstall`).
2. `scripts/icons/generate-icons.mjs` scans `.ts/.tsx/.js/.mjs/.cjs/.vue/.php/.json/.scss/.css` under `resources/ts`, `resources/views`, `app`, `lang`, `routes`, `database`, `config`, plus `themeConfig.ts`, for `tabler-` / `mdi-` / `fa-` tokens.
3. **Static** class-based icons need no extra step — reference the full `prefix-name` and the next build picks it up.
4. **Dynamic or externally-sourced** class-based names (template literals, values from an API/DB, computed names) cannot be scanned. Either add the exact name to `resources/ts/plugins/iconify/icon-allowlist.json` under `"include"`, or switch to the remote `<Icon>` mechanism.
5. `"ignore"` in the allowlist skips an icon **and** silences its warning — used for known false positives (`tabler-align`, the fragment of `` `tabler-align-${...}` ``) and for names rendered from imported SVGs (the 5 `mdi-*` checkbox/radio names in `plugins/vuetify/icons.ts`).
6. Iconify **aliases are resolved** by the generator, so names like `tabler-discount-2` work. A name absent from a set's `icons` map is not necessarily invalid.
7. The generator **warns** about tokens used but unavailable in every set. Treat a warning as a real bug — it means a blank glyph.

---

## 6. What was done

### 6.1 Phase 1 — Prune the icon stylesheet ✅

* Added **`scripts/icons/generate-icons.mjs`** (plain-node ESM, matching the `scripts/i18n/*.mjs` convention): repo-wide scan → merge allowlist → filter per set → write CSS. Preserves the tabler `stroke-width: 2 → 1.5` rewrite from the old script.
* Added **`resources/ts/plugins/iconify/icon-allowlist.json`** (`include` / `ignore`).
* Deleted `resources/ts/plugins/iconify/build-icons.mts` (replaced). `build:icons` is now `node scripts/icons/generate-icons.mjs` — no `tsx`/esbuild dependency.
* Fixed `tabler-funnel` → `tabler-chart-funnel`; alias resolution fixes `tabler-discount-2`.

**Result:** `icons.css` **2,851,248 → 130,729 B (−95.4%)**; rules **6,015 → 315** (313 tabler + 2 mdi; the unused `fa` set is now skipped). Projected entry CSS **3,319,892 → ~599,373 B (−81.9%)**. 321 referenced tokens, **0 unresolved**.

### 6.2 Phase 2 — Stop shipping unused locales ✅

* `plugins/i18n/index.ts`: `en` stays eager (it is the default *and* the universal fallback, so it must be synchronous); `fa-AF`/`ps-AF` become lazy chunks via `import.meta.glob(['./locales/*.json', '!./locales/en.json'])`.
* New exports: `loadLocale(locale: string)` (loads a locale **plus its fallback chain**, validates the code, returns `false` on failure) and `getInitialLocale()`. Existing `getI18n()` / `supportedLocales` / `SupportedLocale` / default export unchanged, so `initCore.ts`, `utils/localization.ts` and `plugins/vuetify/index.ts` keep working untouched.
* `main.ts`: mounts only after `loadLocale(getInitialLocale())` resolves (`.catch().finally()` — no top-level await, and it still mounts if a chunk fails). This prevents a flash of English and a missing-key window.
* `@core/components/I18n.vue`: the language switcher now `await loadLocale(next)` before reassigning `locale`, so a switch never renders untranslated keys.

**Result:** **575,885 B** of locale data (and its compile cost) leaves the entry for every visitor.

### 6.3 Phase 3 — ⚠ partially superseded

**3a ✅** Removed `import * as Api from '@/api'` + `app.config.globalProperties.$api` from `main.ts`; deleted the dead `resources/ts/api-components.d.ts`.

**3b ❌ cancelled.** Removing `import 'vuetify/styles'` would remove Vuetify's base layer and break the UI (see F3). The base layer is dominated by responsive display utilities the app actually uses, so it is not safely reducible, and `$color-pack` / SASS-level trimming could not be visually verified in this environment. **Deliberately not applied** rather than risk a regression under a "don't break features" constraint.

### 6.4 Phase 4 — Guardrails ✅ (manualChunks declined)

* `vite.config.ts`: `chunkSizeWarningLimit` **5000 → 1500** (kB). This flags the pre-fix entry (1,633 kB) while staying quiet for the legitimately large lazy mapbox chunk (1,410 kB). Vite's default is 500; 1500 is a documented budget, not a silencer.
* `package.json` + `pnpm-lock.yaml`: `@iconify/vue` moved `devDependencies → dependencies`.
* **Declined:** `manualChunks` for vuetify. It improves caching, not TBT (every chunk still parses before first render), and would add a request. Left as optional.

### 6.5 Non-goals respected

No changes to routing, auth guards, CASL, print CSS (`printDocument.ts`, `@page`, `@media print`), RTL/`dir` handling, theme persistence, `initCore()`, or fonts. No new runtime dependencies added.

---

## 7. Verification

### Performed here ✅

| Check | Result |
| --- | --- |
| `npm run typecheck` | **24 errors before, 24 after — byte-identical list.** Verified by reverting only my files to `HEAD`, re-running, and diffing. **0 errors introduced**, none in any touched file. |
| Icon generator | **Idempotent** (re-run produces a byte-identical `icons.css`). |
| Token coverage | 321 referenced tokens → 313 rules + 2 mdi + 6 allowlisted/aliased = **0 unresolved**. No warnings emitted. |
| Deliberate blank-icon bugs | Both now render (`tabler-discount-2` via alias, `tabler-chart-funnel`). |
| Lockfile consistency | `@iconify/vue` appears exactly once in the importer, under `dependencies`. |
| Working tree | All edits intact after verification; deletions registered (`D`). |

### Could NOT be run in this environment ⚠

The sandbox is **WSL Linux with a Windows-installed `node_modules`**, so binaries built for the other platform fail:

* `npm run build` / `npm run dev` — `esbuild` native binary mismatch.
* `npx eslint` — `unrs-resolver` native binding missing.

This is an environment limitation, not a code problem. **Run these on the dev machine.**

### Required follow-up on the dev machine

1. `npm run build` — this regenerates the committed `public/build/*` artifacts (221 tracked files) with the pruned CSS and chunked locales. Confirm no `chunk size` warning appears.
2. `npm run typecheck` — expect the same 24 pre-existing errors.
3. `npm run lint`.
4. `npm run i18n:check` (passes today). `npm run i18n:test` fails on the pre-existing missing `en` key noted in §4.
5. Compare `public/build/assets/main-*.js` and `main-*.css` against §3.
6. **Manual visual pass** (the parts I could not verify):
   * Cold load `/login` and `/home` at 4× CPU throttling → record TBT / FCP / LCP / CLS (median of 3) and compare with your baseline.
   * Walk every top-level route and confirm nav icons render (Phase 1).
   * Switch through **all three** languages; reload with each persisted locale; confirm no flash of English, no `[missing:*]` in DEV, no empty labels (Phase 2).
   * Light + dark theme; LTR + RTL.
   * Dialogs / menus / data tables / date pickers / autocompletes still styled correctly (sanity for 3b being left alone).
   * Print an invoice (A5) and a contract (A4) → layout intact.

---

## 8. Progress log

| Phase | Step | Status | Notes / evidence |
| --- | --- | --- | --- |
| — | Research + baseline measurement | ✅ Done | F1–F6 in §4; baseline in §3 |
| 1 | Document icon specification in `AGENTS.md` | ✅ Done | New "Icons" section (§5) |
| 1 | Add subset generator + allowlist | ✅ Done | `scripts/icons/generate-icons.mjs` (plain node) + `icon-allowlist.json`; `build-icons.mts` deleted |
| 1 | Regenerate `icons.css` | ✅ Done | **2,851,248 → 130,729 B (−95.4%)**; 6,015 → 315 rules |
| 1 | Resolve blank-icon bugs | ✅ Done | Alias resolution fixes `tabler-discount-2`; `tabler-funnel` → `tabler-chart-funnel` |
| 2 | `en` eager, others lazy + `loadLocale()` | ✅ Done | `plugins/i18n/index.ts` |
| 2 | Await active locale before mount | ✅ Done | `main.ts` |
| 2 | Wire language switcher | ✅ Done | `@core/components/I18n.vue` |
| 3a | Remove dead `$api` barrel + type | ✅ Done | `main.ts`, `api-components.d.ts` deleted |
| 3b | Drop `import 'vuetify/styles'` | ❌ Cancelled | Would break the base layer — F3 retracted |
| 4 | `chunkSizeWarningLimit` 5000 → 1500 | ✅ Done | `vite.config.ts` |
| 4 | `@iconify/vue` → `dependencies` | ✅ Done | `package.json` + `pnpm-lock.yaml` |
| 4 | `manualChunks` for vuetify | ⏭️ Declined | No TBT benefit; optional |
| — | Typecheck baseline vs. after | ✅ Verified | 24 == 24, 0 introduced |
| — | Production build + visual verification | ⏳ Pending | Cannot run here — see §7 |

### Change log

* **2026-10-06** — Research completed. Baseline captured. Plan written. Icon spec added to `AGENTS.md`.
* **2026-10-06** — **Phase 1 done.** `icons.css` 2,851,248 → 130,729 B. Generator replaced and moved to plain node; allowlist added; alias resolution added; two blank-icon bugs fixed.
* **2026-10-06** — **Phase 2 done.** Non-default locales lazy; `en` stays eager; startup gates on `loadLocale`; switcher awaits.
* **2026-10-06** — **Phase 3a done, 3b cancelled.** F3 retracted after inspecting `@vuetify/loader-shared` and Vuetify component JS — `vuetify/styles` is the base layer, not redundant.
* **2026-10-06** — **Phase 4 done** (guardrail + dependency fix); `manualChunks` declined.
* **2026-10-06** — Verified: typecheck identical to baseline (24 errors, all pre-existing, none in touched files). `vite build` / eslint cannot run in this WSL sandbox (Windows `node_modules`); production build + visual pass handed off per §7.
