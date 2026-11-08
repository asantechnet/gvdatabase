# Progress — Task-035

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-11-08`
- Current focus: `Optional audit colouring implemented end to end on branch feature/optional-audit-coloring; browser/responsive verification remains.`
- Blocker: `None for the implementation. Browser verification was not run; the repository's typecheck baseline has 69 unrelated errors and CompanySettingsTest has one pre-existing route-shadowing failure.`
- Next action: `Review the diff, then verify the legend/rails/changed cells/action in a browser at 390/768/1280 px, LTR/RTL and light/dark.`

## Acceptance criteria progress
- [ ] Given a user with `view_audit_logs` opens any covered register with audited rows, then each row carries exactly one rail state derived from its latest audit entry: Created is green, Updated is amber, Deleted is red when the register already returns a deleted row, and Restored is amber with the existing restored label; rows without history or without permission are neutral. — Evidence: `AuditTrailController` selects `MAX(id)` per record under the caller's audit scope; `useAuditTrail` maps the latest row; shared-table and raw-page classes use `success`/`warning`/`error` theme tokens. NOT BROWSER-VERIFIED.
- [ ] Given an Updated row, then only the cells whose visible column maps to a changed audit field receive the change emphasis, each with an accessible label/tooltip naming the field and its before → after values; changed fields that have no visible column are listed in the row's audit summary, and unchanged cells are not marked. — Evidence: `changedFieldForColumn`, `AuditTrailCell` and the row-action summary; only matched header columns are wrapped. NOT BROWSER-VERIFIED.
- [ ] Given a Created row, then the row is identifiable as created but no fabricated field diff is shown; given a Deleted row that the current table scope returns, then the row is identifiable as deleted, read-only and its audit action opens the deletion entry. — Evidence: `AuditTrailPresenter::presentTrail` returns `changed_fields: []` for created rows; `AuditTrailTest::test_returns_the_latest_audit_entry_per_record` and `test_deleted_records_keep_their_deletion_entry` pass. UI is not browser-verified.
- [ ] Given a row with at least one audit entry and the read permission, then a labelled row action opens `/audits?audit={latestAuditId}` and the exact audit detail is shown, even when that audit is not on the Audits page's current list page; without an audit id the action is absent or disabled with an explanation rather than navigating to a blank search. — Evidence: `AuditTrailButton` navigates to the named `audits` route with `query.audit`; absent when no trail. NOT BROWSER-VERIFIED.
- [ ] Given a user without `view_audit_logs` or a caller whose audit read returns 403, then no legend, rail, changed-cell emphasis or audit action is rendered, no error toast appears, and the table's existing pagination, sorting, filters, selection, print and mobile cards are unchanged. — Evidence: `AuditTrailTest::test_audit_read_permission_is_required` returns 403; `useAuditTrail` gates on `canAccess('view_audit_logs')`, sends `X-Suppress-Error-Notification` and catches failures. No-toast/layout behaviour is NOT BROWSER-VERIFIED.
- [ ] Given a register page with 25-100 rows, then the table issues at most one row-trail enrichment request per loaded page/filter state (never one per row), sends no more than 100 distinct ids, supersedes stale requests, and the server returns only the caller-visible latest entry per id. — Evidence: `useAuditTrail` watches one page signature, dedupes/slices 100 ids and ignores stale sequences; `AuditTrailTest::test_a_page_of_rows_is_enriched_with_a_bounded_query_count` (25 rows) and `test_team_reviewer_receives_only_visible_employee_trails` pass.
- [ ] Given the coverage matrix, then every `AuditRegistry` key plus `account_deactivations` and `audit_settings` is represented: shared-table registers are automatic, the five raw-table pages are explicitly integrated, parent/detail-only keys point to their parent record action, and `audit_settings` is identified as the audit-configuration surface rather than silently dropped. — Evidence: central map covers 38 list endpoints; the three contract pages already use the shared table; `educational-master-data.vue` and `academy-data.vue` use `useAuditTrail` explicitly. PARTIAL: parent/detail-only keys rely on their parent row; `account_deactivations` and `audit_settings` return no trails because neither is an audited registry model.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark themes, then the rail/legend/action remain readable without horizontal overflow, state is not conveyed by colour alone, changed-cell tooltips are keyboard reachable, focus order is preserved and reduced-motion preferences are respected. — Evidence: logical inset properties, text legend + icons, focusable changed cells, `prefers-reduced-motion` reset. NOT BROWSER-VERIFIED.
- [ ] Given auditing is disabled for a resource, or the resource has only pre-existing history, then the table renders neutrally without an error; when history exists and the user has permission, the action can still open it; no list scope or deleted-row visibility is changed. — Evidence: `AuditTrailTest::test_existing_history_survives_disabling_a_resource_and_new_rows_stay_neutral` passes; no list controller was changed.
- [ ] Given the completed implementation, then `php artisan test --filter=AuditTrailTest`, the existing audit management/readability tests, `npm run typecheck`, the icon build warning check and the manual register checks in the verification plan pass, and no duplicate paginator, toast, second audit viewer or per-row fetch is introduced. — Evidence: `AuditTrailTest` 8/36 PASS; `AuditManagementTest` 7/38 PASS; `npm run build:icons` PASS with no missing-icon warning. `npm run typecheck` still exits 2 from 69 pre-existing errors in unrelated files; no error names a changed file. Manual register checks NOT RUN.

## Work completed with evidence
- Added the opt-in `settings.audit_coloring_enabled` column (default `false`), validation, model cast/fillable, settings resource, public branding exposure and Settings → Operations switch.
- Added `GET /api/audit-logs/row-trails` (`AuditTrailController`) for 1–100 ids, one grouped latest-id query, `view_audit_logs` and the existing team scope. The literal route is registered before `audit-logs/{auditLog}`.
- Added `AuditPresenter::presentTrail` so a 100-row page does not load every audited model; created rows carry no fabricated diff.
- Added the typed client, central URL→resource map, non-enumerable Axios row tag, batching composable, legend, changed-cell wrapper and permission-gated deep-link action.
- Extended the shared responsive table with the rail, legend, changed-cell emphasis, audit action column and mobile-card rail. The audit column appears only when the loaded page has at least one audited row.
- Integrated the two remaining raw catalogue tables (`educational-master-data.vue`, `academy-data.vue`); `contract_list.vue`, `service-contracts.vue` and `cancelled_contracts.vue` already render the shared table.
- Added global, namespaced CSS using logical properties and theme tokens; no print CSS or `@page` rule was touched.
- Added `tests/Feature/AuditTrailTest.php` covering latest-per-record, deleted state, permission, validation, disabled audit, bounded queries, team scope and the settings toggle.

## Files changed and why
| File | Change |
|---|---|
| `database/migrations/2026_11_08_000001_add_audit_coloring_enabled_to_settings.php` | Opt-in boolean setting, default off. |
| `app/Models/Setting.php`, `app/Http/Requests/UpsertSettingRequest.php`, `app/Http/Resources/SettingResource.php`, `app/Http/Controllers/SettingController.php` | Persist, validate and expose the toggle (including the public branding gate). |
| `app/Http/Controllers/AuditTrailController.php` | New bounded latest-per-record audit read with permission and team scope. |
| `app/Support/AuditPresenter.php` | Compact `presentTrail` serializer without model fan-out. |
| `routes/api.php` | Register the literal row-trails route before the audit detail route. |
| `resources/ts/api/auditTrail.ts` | Typed row-trail contract. |
| `resources/ts/utils/auditResources.ts` | One list-URL→registry map and proxy-safe row tagging. |
| `resources/ts/utils/auditTrail.ts` | Event state, column matching, change formatting and cell helpers. |
| `resources/ts/composables/useAuditTrail.ts` | One quiet batched request per page, stale-response guard, failure fallback. |
| `resources/ts/components/audit/*` | Legend, changed-cell wrapper and exact-audit action. |
| `resources/ts/components/responsive-data-table/responsiveDataTable.ts` | Shared rail, legend, changed-cell emphasis, audit column and mobile card rail. |
| `resources/ts/api/axios.ts` | Tag register list rows without mutating payloads. |
| `resources/ts/composables/useBranding.ts`, `resources/ts/api/setting.ts` | Read/write the new display flag. |
| `resources/ts/pages/setup/settings.vue` | Settings → Operations toggle and form persistence. |
| `resources/ts/pages/educational-master-data.vue`, `resources/ts/pages/academy-data.vue` | Raw-table rail, changed cells and audit action. |
| `resources/ts/utils/apiAuthorization.ts` | Client preflight grant for the new read. |
| `resources/styles/styles.scss` | Logical-property, theme-token rail/legend/cell styles. |
| i18n locale files | Toggle label/hint in en, fa-AF, ps-AF. |
| `tests/Feature/AuditTrailTest.php` | New contract, permission, scope, budget and toggle coverage. |
| `AGENTS.md` | Maintained behaviour note for the optional feature. |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-08 | `php -l` on all changed PHP files | Local | PASS | Terminal output | No syntax errors. |
| 2026-11-08 | `php artisan test --filter=AuditTrailTest` | Local, RefreshDatabase | PASS | Terminal output | 8 tests, 36 assertions. |
| 2026-11-08 | `php artisan test --filter=AuditManagementTest` | Local, RefreshDatabase | PASS | Terminal output | 7 tests, 38 assertions; review/undo/scope regressions unchanged. |
| 2026-11-08 | `npm run typecheck` | Local | FAIL (baseline) | `/tmp/typecheck-after.log` | 69 pre-existing errors in `staff_list.vue`, `applicant-case.vue`, `support.vue`; none in changed files. |
| 2026-11-08 | `php artisan test --filter=AuditReadabilityTest` | Local, RefreshDatabase | PASS | Terminal output | 4 tests, 25 assertions. |
| 2026-11-08 | `php artisan test --filter=CompanySettingsTest` | Local, RefreshDatabase | 10/11 PASS | Terminal output | Pre-existing, unrelated: `main` registers `/company-profile` twice; the later `view_settings,view_contracts` route shadows the open route, so the test receives 403. This task did not modify that route. |
| 2026-11-08 | `npm run build:icons` | Local | PASS | Terminal output | 297 references, no missing-icon warning; generated `icons.css` unchanged after the run. |
| 2026-11-08 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Terminal output | 45 tasks, 0 errors; INDEX.md and Progress.md statuses validated. |
| 2026-11-08 | Locale JSON parse (en, fa-AF, ps-AF) | Local | PASS | Terminal output | All three valid. |
| 2026-11-08 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-11-08 | Browser checks at 390/768/1280 px, RTL/LTR, light/dark | Browser | NOT RUN | — | Manual visual/accessibility pass remains. |

## Decisions and deviations
- The toggle lives in Settings → Operations and defaults off, so existing installations see no visual change until an administrator opts in.
- The public company-branding payload carries the boolean solely for UI gating; editing still requires `edit_settings`.
- One list URL maps to one resource key. Unmapped or ambiguous endpoints fall back to no rail rather than guessing, matching the task's safety rule.
- `account_deactivations` and `audit_settings` are not audited registry models; the row-trails read returns an empty set for them. Their history remains on the Audits page.
- The three contract register pages already use the shared responsive table, so no page file needed editing for them.
- The global rail CSS is in `resources/styles/styles.scss` (already loaded globally) rather than a route stylesheet, so it cannot leak print layout rules.

## Blockers and open questions
- Browser verification remains (viewport/direction/theme, tooltip focus, mobile cards and no-history tables).
- `npm run typecheck` has a pre-existing baseline failure unrelated to this task.
- `CompanySettingsTest` has one pre-existing failure: `main` registers `/company-profile` twice and the permission-gated duplicate shadows the open route. It is outside this task's route change.
- Parent/detail-only audited resources (`comments`, `subtasks`, settings, etc.) rely on their parent row action; no orphan table was invented.

## Handoff notes
- Branch: `feature/optional-audit-coloring` off `main`.
- Enable the feature in Settings → Operations before checking registers; it is off by default.
- The API read is quiet by design: a 403/500/offline response must leave the table unchanged and produce no toast.
- Browser checks should include an updated row with one visible and one hidden changed field, a created row, a deleted/archived row on a surface that already returns it, and a table with no audit history.

## Activity log (append-only)
- `2026-11-07` Created Task-035 from the audit-rail request; status `Ready`.
- `2026-11-08` Implemented optional audit colouring end to end on `feature/optional-audit-coloring`; backend tests and icon build pass, browser verification pending; status `Review`.
- `2026-11-08` Ran the task validator (45 tasks, 0 errors) and recorded the unrelated `CompanySettingsTest` route-shadowing baseline failure.
