## Agency overview dashboard refinement

- Date: 2026-10-25
- Status: IMPLEMENTED; VALIDATION PARTIALLY BLOCKED

### Scope and decisions
Remove the six shortcut links and their exclusively used translations/data (prospect count), preserving analytics which still consume income, approvals, and workload aggregates. Replace the full-width filter card with a reusable 460px responsive date dropdown. Remove counselor/country/university option fetches and inputs; retain backend optional filters for existing API callers and authorization regression coverage. Refresh uses applied dates, not drafts. Keep existing live operational metric semantics and tenant/employee authorization.

### Impact review
Used repository reference searches and manual tracing: educational-dashboard.vue -> educationalDashboard.ts -> EducationalDashboardController -> EducationalDashboardTest; ErpPageHeader actions slot; shared Footer -> both staff layouts. Dashboard-only translation keys can be deleted; labels reused by analytics/other pages must remain. No schema/migration change needed. Skeleton must share KPI and analytics grids and preserve actual panel height during refresh. Screen layout changes must not alter document print behavior.

### Plan and acceptance criteria
- [x] Remove shortcut row and prospect aggregate/response/type; remove unused dashboard translations and period-help copy in all three languages.
- [x] Add reusable date dropdown with stacked dates, adjacent Apply/Clear, invalid range prevention and draft/applied separation; align Filter/Refresh beside heading.
- [x] Compact KPI cards, pair icons with values, maintain one horizontally scrollable mobile row without scroll controls.
- [x] Use a consistent 16px analytics gap (8px greater than previous dense grid).
- [x] Add matching wave skeleton component on initial load and refresh; retain measured content height on refresh.
- [x] Add translated current-year Asan Tech linked footer to both staff layouts.
- [ ] Validate backend aggregate regression tests, PHP syntax, frontend typecheck, translation checks, icons, production build and diff.

### Risks / validation limits
Browser daemon failed in prior task; attempt available browser verification if practical and explicitly record gaps. Preserve API permission/employee scope and date boundaries. Existing date semantics (live KPI totals versus dated cohorts/financials) remain unchanged.

### Progress
- 2026-10-25: Reviewed shared header/date controls, dashboard endpoint and regression tests; saved plan before implementation.

- 2026-10-25: Removed six shortcuts, master-data/employee option requests, prospect count query and API field. Updated regression test to assert no visitors SELECT and no prospect response field, while retaining contract-expiry assertions.
- 2026-10-25: Implemented reusable 460px viewport-constrained date dropdown; stacked native date inputs, adjacent Apply/Clear, draft reset on opening, invalid range prevention, applied-range-only Refresh. API contract exposes dates/stale threshold only; backend optional filters retained for compatibility.
- 2026-10-25: Paired KPI icons with values, reduced padding, shared responsive grid with one mobile scroll row and no scrollbar/scroll controls. Analytics use one 16px gap, replacing dense 8px gutters. Wave skeleton shares those grids and measures existing KPI/panel heights before refresh; honors reduced-motion preference.
- 2026-10-25: Added translated current-year developer footer with Asan Tech link to both staff layouts; removed old branding/copyright footer and unused dashboard labels/help copy in English, Dari and Pashto.

### Validation results
- `npm run typecheck`: passed after initial implementation and final repeat (exit 0).
- `npm run i18n:check`: passed after footer brand translation was added.
- `npm run i18n:test`: fails at existing `ps-AF:documents.payslip.workedDays` placeholder mismatch. Compared that message with HEAD and confirmed identical; outside dashboard scope.
- Direct Vue SSR rendering with vue-i18n Translation: passed for footer current year and developer link in en/fa-AF/ps-AF.
- `php -l app/Http/Controllers/EducationalDashboardController.php` and `php -l tests/Feature/EducationalDashboardTest.php`: passed.
- `php artisan test --compact --filter=EducationalDashboardTest`: blocked before tests by MySQL connection, including escalated retry.
- `vendor/bin/phpunit --filter=EducationalDashboardTest`: seven initialization errors, zero assertions, same MySQL connectivity failure. SQLite fallback unavailable (`pdo_sqlite` not installed). Harness unchanged.
- `npm run build:icons`: passed, 888 files scanned, no unavailable icon warnings.
- `npm run build`: passed, 1344 app modules and 82 service-worker modules; generated committed public assets refreshed (246 precache entries).
- `git diff --check`: passed.
- `playwright-cli -s=agency-overview open http://localhost:8000`: browser daemon exited with code 1; live browser behavior and screenshots remain unverified.

### Completion and remaining checks
Implementation is complete. Required source/build/translation audit checks passed. Backend assertions and live visual checks must be rerun when local MySQL and browser automation are available. Full translation runtime suite remains blocked by the pre-existing payslip message. No database schema, authorization boundary, polling, or PWA lifecycle change.
