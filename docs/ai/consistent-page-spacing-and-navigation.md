## Task: Consistent page spacing and navigation

- **Task ID:** consistent-page-spacing-and-navigation
- **Created / Last updated:** 2026-10-25
- **Status:** COMPLETED
- **Outcome:** Full-width workspaces with consistent responsive gutters and a quieter, clearer navigation shell.

### 1. Requirements and acceptance criteria
- [x] Main branch checked out; existing work preserved.
- [x] Content uses padding rather than boxed/page-specific maximum widths: 6px mobile, 24px tablet, 32px laptop/desktop.
- [x] Guide sits beside horizontal logo or beside vertical-layout Search; removed from menu links.
- [x] Language options show flags with translated names; active language shows flag only on mobile and flag/name above mobile.
- [x] Navbar fullscreen control and its dedicated logic removed; backend searched for matching functionality.
- [x] Unread count overlays the bell; navigation distributes free space evenly and retains overflow scrolling.
- [x] Horizontal dropdowns use theme-aware borders without shadows.

### 2. Scope and non-goals
- Shared authenticated layouts, workspace root widths, language picker, notification badge, horizontal dropdowns.
- Preserve authentication forms, readable inner text limits, editor/document dimensions, print rules, polling, API contracts and PWA lifecycle. No unrelated redesign or backend schema changes.

### 3. Project findings
- Clean tree; switched feature/multi-tenancy-per-office to main (one existing local commit ahead of origin).
- Shared boxed-content placeholder caps at 1440px; education-workspace and individual page roots impose additional inconsistent caps.
- HorizontalNav and VerticalNav currently place Guide among navigation items. Layout navbar slots own logo/search controls.
- I18n and Notifications resolve to resources/ts/@core/components through generated component declarations.
- NavbarFullscreenToggle uses VueUse browser fullscreen; searches of app/routes/database/lang found no related backend feature.
- Horizontal dropdowns teleport to body; styles must target horizontal-nav-popper-content directly.

### 4. Graph and impact review
- Used rg reference searches/manual import tracing; no graph skill available.
- default.vue -> horizontal/vertical layouts -> layout shell -> navigation groups/popover; navbar -> I18n/Notifications; Notifications caller owns polling and remains unchanged.
- Shared placeholder affects navbar/footer/content alignment; screen-only layout rules prevent print interference. Page-root caps removed individually, leaving internal controls and document dimensions intact.
- Static review does not replace browser rendering checks.

### 5. Decisions and assumptions
- “Maximize” means navbar browser-fullscreen action, not contract editor expansion or Vuetify dialog fullscreen support.
- Vertical placement means beside Search in the vertical layout's topbar.
- English uses GB flag; Dari/Pashto use Afghanistan flag, retaining distinct names in the menu and accessible trigger labels.
- Preserve cookie/customizer compatibility by making shared gutters uncapped regardless of saved boxed/fluid setting.

### 6. Implementation plan
- [x] Remove shared screen width cap and normalize responsive content/header/footer gutters.
- [x] Remove workspace-root caps in shared and page styles.
- [x] Relocate Guide; delete obsolete nav-list guide elements and fullscreen component.
- [x] Add active-language flag/name trigger and flag/name choices with accessible mobile presentation.
- [x] Position numeric badge over bell; distribute nav links and replace dropdown shadow with border.
- [x] Run typecheck, icon generation and production build; review diff and update this record.

### 7. Validation plan and results
- npm run typecheck; npm run build:icons; npm run build.
- Review screen-only changes for RTL logical spacing, dark/light variables, overflow navigation, preserved print/polling behavior.
- Browser checks if local environment permits; record any missing validation explicitly.

### 8. Risks and mitigations
- Tight mobile header: compact language trigger and unchanged icon button sizing.
- Very wide content: remove only page-root caps; preserve inner text/form/document limits.
- Existing saved boxed preference: eliminate screen cap at common shell rather than relying only on a new default.
- Build refreshes committed assets; use project build rather than editing generated output.

### 9. Progress log
- 2026-10-25 — DISCOVERY: Reviewed layout/data dependencies and backend fullscreen searches; switched main after sandbox-approved git operation.
- 2026-10-25 — PLAN: Saved full implementation and validation plan before code edits.

### 10. Completion summary
- Delivered responsive screen-only gutters, uncapped workspaces, relocated Guide, active language flags/names, numeric bell badge, evenly spaced links and bordered dropdowns. Removed dedicated fullscreen component and imports; no backend fullscreen implementation existed.
- All implementation criteria satisfied by source review; production compilation and type checking passed.
- Browser visual verification remains unverified due to Playwright daemon startup failure. Mobile/tablet/desktop, RTL/LTR and both themes should still receive a live visual check before deployment.
- Generated public/build, public/sw.js and public/version.json refreshed by the production build; no PWA lifecycle behavior changed.


- 2026-10-25 — PROGRESS: Implemented responsive screen gutters, removed page-root caps, relocated Guide, removed fullscreen control, adjusted dropdown border and unread badge.

- 2026-10-25 — PROGRESS: Final gutter implementation lives in the shared boxed-content placeholder, covering saved boxed/fluid preferences and existing outer navbar/footer containers without duplicate padding. All responsive overrides are screen-only.
- 2026-10-25 — VALIDATION: `npm run typecheck` passed (exit 0).
- 2026-10-25 — VALIDATION: `npm run build:icons` passed; 885 files scanned, 294 references bundled, no unavailable-icon warnings.
- 2026-10-25 — VALIDATION: `npm run build` passed (1339 app modules, 82 worker modules; 247 precache entries). Generated assets retained as required by project convention.
- 2026-10-25 — VALIDATION: `git diff --check` passed. Checked compiled default-layout CSS confirms 6/24/32px responsive gutters and screen-only max-inline-size removal.
- 2026-10-25 — LIMITATION: `playwright-cli open http://localhost:8000` failed during browser startup: daemon process exited with code 1. No authenticated browser session, screenshot, or visual pass is claimed.
- 2026-10-25 — COMPLETE: Source changes and required compilation checks finished; browser verification limitation retained above. No changes to backend schemas, notification delivery, printing, or update lifecycle.
