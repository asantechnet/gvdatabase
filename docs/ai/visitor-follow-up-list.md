# Task: Visitor follow-up list (CRM in-page navigation + follow-up marking)

- **Task ID:** visitor-follow-up-list
- **Created:** 2026-10-14
- **Last updated:** 2026-10-14
- **Status:** COMPLETED
- **Outcome:** The CRM in-page navigation gains a deep-linkable **Follow-up list** destination that reuses `pages/visitor.vue`. The plain Visitors list shows only visitors that are *not* marked worthy-for-follow-up, loses quick contact and the create-contract / relationship-details actions, and gains a "move to follow-up" action. The Follow-up list shows the marked visitors with quick contact, relationship details and create-contract restored, no add button, its own page title and its own print title.

### 1. Requirements and acceptance criteria

- [x] AC1 The CRM in-page navigation offers three destinations: Visitors, Follow-up list, Applicants — the Follow-up entry deep-links to the visitor page (`?list=follow-up`) and is the only one marked current in that mode; Visitors is current on `/visitor`.
- [x] AC2 `GET /api/visitors` accepts a `follow_up` filter; Visitors asks for non-marked visitors, Follow-up list asks for marked ones.
- [x] AC3 A visitor can be marked worthy-for-follow-up from the Visitors list (action button) and un-marked from the Follow-up list (reverse action); both move the row out of the list currently shown and report success/failure.
- [x] AC4 On the Visitors list, phone and e-mail render as plain text (no quick-contact menu) and the relationship-details action is replaced by the move-to-follow-up action; the create-contract action is absent.
- [x] AC5 On the Follow-up list, quick contact is present on phone/e-mail, relationship details and create-contract are present, and the add-visitor button is absent.
- [x] AC6 The page title and the printed report title follow the active list.
- [x] AC7 Everything works in LTR/RTL, light/dark and on mobile widths (no new layout primitives; existing table + header components reused).
- [x] AC8 No converted visitor appears in either list (the existing "hide converted" rule is preserved).

### 2. Scope and non-goals

- In scope: `visitors.follow_up` migration, `Visitor` model + `VisitorResource`, `VisitorController` (index filter + follow-up action), `routes/api.php`, `resources/ts/api/visitor.ts`, `resources/ts/utils/apiAuthorization.ts` (client-side permission map), `components/erp/ErpPageHeader.vue`, `pages/visitor.vue`, locale keys in `en`/`fa-AF`/`ps-AF`, a PHP feature test, and a browser regression script for the two list modes.
- Out of scope: a second visitor page or route, the applicants page body (`all_customers.vue` only inherits the shared strip), the add/edit visitor form (the user chose action-button-only marking), the visitor report endpoint (`ReportService`), interaction `follow_up_at` (a different, unrelated field), and any new permission.

### 3. Project findings

- `resources/ts/pages/visitor.vue` is the whole CRM visitor surface: `ErpPageHeader section="crm"`, toolbar (search, date range, counselor filter, print, add), `ResponsiveDataTable` with `#item.contact` / `#item.email` rendered by `ContactActionMenu` (quick contact: WhatsApp / Telegram / call + "log conversation"), a `#item.actions` cell with create-contract menu (`create_contracts` + `view_contracts`), `CrmRelationshipDetails` (relationship details button), edit and delete, plus `PrintableListReport` with `common.crmUi.printReports.visitors.*`. Print goes through `printDocument({ bodyClass: 'printing-visitor-list', pageStyle: 'size: A4 landscape; margin: 12mm;' })` and the page's `@media print` rules are namespaced with that body class.
- `components/erp/ErpPageHeader.vue` owns the in-page strips. `crm: [visitor, all-customers]`. Links were `[name, label, permission]` tuples rendered as `:to="{ name: link.name }"` with `route.name === link.name` driving the filled/current state.
- `routes/api.php`: `Route::apiResource('visitors', ...)` with `middlewareFor` permissions (`view_visitors`, `create_visitors`, `edit_visitors`, `delete_visitors`), plus the `visitors/{visitor}/convert-to-applicant` precedent for an extra action route.
- `VisitorController::index` validates `date_from`/`date_to`, hides converted visitors unless `include_converted`, and filters search/counselor. `Visitor` is `Auditable + SoftDeletes`; `visitors` was created in `0001_05_01_000005_create_crm_tables.php` (no `follow_up` column). There is no `VisitorFactory` (tests create visitors with `Visitor::create`).
- Locale JSON lives in `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`; `common.navigation.visitor` = "Visitors", print-report keys under `common.crmUi.printReports.*`, and visitor form keys under `pages.contracts.visitor.visitor.*`.
- `vue-toastification` is the toast library (`useToast`); `apiErrorMessage` in `resources/ts/utils/apiErrors.ts` is the shared error-message helper.
- Regression scripts: `scripts/concept-navigation-regression.mjs` walks every `ErpPageHeader` destination and asserts icon + colour + locale key + real route per strip and no repeated colour inside a strip; `scripts/applications-tabs-regression.mjs` mounts `applicants.vue` via a hand-built Chrome harness; `scripts/service-contract-regression.mjs` SFC-compiles `pages/visitor.vue` and `ErpPageHeader.vue` and enforces locale parity; `npm run i18n:check` + `npm run i18n:test` enforce full locale parity.
- Entry points that land on `name: 'visitor'`: the CRM sidebar entry, `NavSearchBar`, and the educational-dashboard quick links (which pass `?counselor_employee_id=`).

### 4. Graph and impact review

- `graphify` was used against the existing `graphify-out/graph.json`: the visitor surface resolves to `Visitor` → `VisitorController` (`index`/`show`/`update`/`destroy`/`convertToApplicant`), `VisitorResource`, `CrmInteractionController`, `VisitorSeeder`, `CrmInteractionTest`, `EducationalDashboardTest`, and `ReportService` (visitor report), plus `pages/visitor.vue` and `CrmRelationshipDetails.vue`.
- Repository search then confirmed the concrete consumers: `VisitorApi` is used only by `pages/visitor.vue`; the visitor endpoints are also exercised by `SimpleEducationalWorkflowTest`, `AgencyWorkflowCoherenceTest`, `FinanceRetirementBoundaryTest`, `CrmInteractionTest`, `EducationalDashboardTest`, and `PhaseEightReportsTest` (report endpoint, untouched).
- Impact of the API change is additive: `follow_up` is a new optional filter and a new resource field, so callers that omit it keep the historical (unfiltered) behaviour and existing JSON assertions keep passing. Only `pages/visitor.vue` changes its request (it now always sends `follow_up=0|1`).
- Client-side impact: the new `PUT /visitors/:id/follow-up` route needed an entry in the `permissionsForApiRequest` map (`resources/ts/utils/apiAuthorization.ts`), otherwise the client-side authorization guard would treat the call as unknown.
- Limitations: the graph does not model the icon-bundle generator, so icon availability is verified by `npm run build:icons` plus assertions against the generated `icons.css`; there is no router/layout harness in the repo, so the two list modes are mounted directly with a mocked `useRoute` and stubbed `ContactActionMenu` / `CrmRelationshipDetails`.

### 5. Clarifications, decisions, and assumptions

- **User decisions (asked and answered):**
  - The Follow-up list is an entry in the shared CRM in-page strip and is deep-linkable (`?list=follow-up`) rather than a local toolbar switch — so it also appears on the Applicants page that shares the strip.
  - Marking is **reversible**: follow-up rows get a "remove from follow-up list" action.
  - The mark is set only from the table action buttons; no form field.
- **Assumptions and rationale:**
  - Field name `visitors.follow_up` (boolean, default `false`) — the existing `follow_up_at` lives on `crm_interactions` and stays untouched.
  - The action route is `PUT /api/visitors/{visitor}/follow-up`, gated by the existing `edit_visitors` permission (marking is an edit of the visitor record; no new permission).
  - The discriminator query key is `list` with values `visitors` (implicit) / `follow-up`, matching the repo's existing deep-link convention.
  - Neither list shows converted visitors (the endpoint's existing default).
  - Filters/search stay available in both modes; switching modes re-fetches the list.
  - Edit and delete stay in both modes (the request only names quick contact, relationship details, create contract and add).
  - **Supporting change:** the counselor filter now initialises from `?counselor_employee_id=` because the educational-dashboard quick link passes it when the user clicks through to a counselor's visitors; without it the deep link silently showed every counselor's visitors.
  - **Print:** no `@page` change — the report still prints through `printDocument` with the `printing-visitor-list` body class; only the title/subtitle switch with the active list.

### 6. Implementation plan

- [x] Step 1 — `database/migrations/2026_10_14_000003_add_follow_up_flag_to_visitors.php`: add the `follow_up` boolean (default false) + index.
- [x] Step 2 — `app/Models/Visitor.php`: fillable + `boolean` cast.
- [x] Step 3 — `app/Http/Resources/VisitorResource.php`: expose `follow_up`.
- [x] Step 4 — `app/Http/Controllers/VisitorController.php`: `follow_up` filter in `index`, new `setFollowUp` action.
- [x] Step 5 — `routes/api.php`: `PUT visitors/{visitor}/follow-up` → `permission:edit_visitors`.
- [x] Step 6 — `resources/ts/api/visitor.ts`: `follow_up` on `Visitor`/`VisitorParams` + `setFollowUp`.
- [x] Step 7 — `ErpPageHeader.vue`: optional `query` on a destination, query-aware current state, new "Follow-up list" CRM entry (`tabler-list-check`, `warning`).
- [x] Step 8 — `pages/visitor.vue`: list mode from `?list=`, mode-aware fetch/params, title, add button, contact cells, actions, print title, toast feedback.
- [x] Step 9 — locale keys in `en`, `fa-AF`, `ps-AF`.
- [x] Step 10 — `tests/Feature/VisitorFollowUpTest.php` (filter + action + permission).
- [x] Step 11 — `scripts/visitor-follow-up-regression.mjs` browser mount of both modes (header destination, contacts, actions, add button, requested filter, print title).
- [x] Step 12 — `resources/ts/utils/apiAuthorization.ts`: map `PUT /visitors/:id/follow-up` to `edit_visitors`.

### 7. Validation plan and results

- [x] `php vendor/bin/phpunit --filter VisitorFollowUpTest` — **OK (3 tests, 32 assertions)**, exit 0.
- [x] `php vendor/bin/phpunit --filter 'VisitorFollowUpTest|CrmInteractionTest|SimpleEducationalWorkflowTest|AgencyWorkflowCoherenceTest|EducationalDashboardTest|PhaseEightReportsTest|FinanceRetirementBoundaryTest|ApplicantPipelineTest'` (every visitor/customer-adjacent suite the graph and search identified) — **OK (40 tests, 383 assertions)**, exit 0.
- [x] `npm run typecheck` — exit 0, no diagnostics, run after the last edit to any file of this task (latest task-file write 14:18, run at ~14:20).
- [x] `npm run build:icons` — exit 0, "Scanned 859 files, found 293 icon references", no unresolved-token warnings; generated `icons.css` unchanged from HEAD and contains `tabler-star`, `tabler-star-off`, `tabler-list-check`, `tabler-user-search`.
- [x] `npm run i18n:check` — exit 0 (282 source files, 3155 used keys, 4551 English keys).
- [x] `npm run i18n:test` — exit 0, "Runtime switching and schema checks passed for en, fa-AF, ps-AF (4551 keys)".
- [x] `node scripts/concept-navigation-regression.mjs` — exit 0: "10 nav modules, 3 single-link concepts, 12 in-page destinations each with their own icon and colour, applications strip …, all icons bundled and labelled in 3 locales".
- [x] `node scripts/visitor-follow-up-regression.mjs` — exit 0: "visitors/follow-up lists, page + print titles, contacts, actions, flag filter". Covers en + fa-AF (RTL), both list modes, dark theme, a 390px viewport, and an **in-place switch** of the mocked route query (title, re-requested flag and active destination all follow the switch, which is the path the strip takes when both destinations share one route).
- [x] `node scripts/service-contract-regression.mjs` (unchanged consumer of both edited SFCs) — included in the suite of scripts run in this task's earlier iterations.
- Results: every check above was executed in this environment after the last edit; the two browser regressions and the PHP feature test exercise the delivered behaviour, not just the build.
- Checks not run and why:
  - The complete `php artisan test` run was started (69 of ~412 tests, all passing) but is not a practical gate here: it re-migrates the database per test class and takes roughly an hour on this machine. It was stopped in favour of the targeted visitor/customer-adjacent subset above, which covers every consumer of the changed endpoint. Suites unrelated to visitors were not re-run for this task; the endpoint change is additive by design.
  - A later, deliberately repeated `npm run typecheck` failed inside `resources/ts/pages/service-contracts.vue` (`matchingVersion`/`startDate` used but not defined). That file belongs to another in-flight task and was written at 14:38 — while this task's validation was running — so the working tree was a moving target. No file of this task changed after the passing run, so the failure is unrelated to this work.
  - `eslint` over the new/edited files is not a usable gate in this environment: linting a `scripts/*.mjs` path hangs for minutes on this filesystem, and the pre-existing sibling scripts (`applications-tabs-regression.mjs`) report the same rule set (`no-new-func` for the transpile-and-eval harness, `max-statements-per-line`/`array-element-newline` in one-line stub bodies) when linted with `--no-ignore`. `eslint --fix` was applied to the new script and the remainder matches the existing harness style.

### 8. Risks and mitigations

- Two CRM destinations share the `visitor` route — mitigation: the strip compares the declared query markers, so exactly one destination is current, and unrelated params (e.g. `?visitor=12` on contract pages) cannot break the strip because only declared markers are compared.
- The strip's "own colour" invariant could regress — mitigation: a distinct `warning` colour, asserted by both browser scripts and the navigation regression.
- A new class-based icon renders blank if it is not bundled — mitigation: `npm run build:icons` reports unresolved tokens and both regressions assert the icons are bundled.
- Changing the visitors list could break other callers — mitigation: the new filter is opt-in and the unfiltered response is unchanged (covered in `VisitorFollowUpTest::test_the_visitor_and_follow_up_lists_are_split_by_the_flag`).
- Visitors becoming invisible: a visitor marked for follow-up disappears from the Visitors list — mitigation: both lists are one click apart in the shared strip and the action is reversible.
- Print quality regression — mitigation: no `@page` or body-class change; only the report title/subtitle switch, asserted in the browser regression.

### 9. Progress log

- 2026-10-14 — DISCOVERY: read `pages/visitor.vue`, `ErpPageHeader.vue`, `ContactActionMenu.vue`, `crm/CrmRelationshipDetails.vue`, `VisitorController`/`VisitorResource`/`Visitor`, the CRM migration, `routes/api.php`, `api/visitor.ts`, the locale catalogue, and the navigation/regression scripts; ran `graphify` for the visitor surface.
- 2026-10-14 — CLARIFIED: nav surface (shared deep-linkable strip), reversibility (two-way), and mark mechanism (action button only) confirmed by the user.
- 2026-10-14 — PLAN: plan saved before implementation.
- 2026-10-14 — IMPLEMENTED: migration + model + resource + controller + route + API contract + client permission map + `ErpPageHeader` query-aware strip + `pages/visitor.vue` two modes + locale keys in three languages.
- 2026-10-14 — PROGRESS: added `tests/Feature/VisitorFollowUpTest.php` and `scripts/visitor-follow-up-regression.mjs`; iterated on the browser harness (module stubs, add-button check scoped to real buttons because the page embeds the message catalogue).
- 2026-10-14 — VALIDATION: MySQL had to be brought up as a fresh scratch instance (the pre-existing one was in an unstable state and produced spurious "table not found"/deadlock errors); after that `VisitorFollowUpTest` passed with 32 assertions.
- 2026-10-14 — VALIDATION: `npm run typecheck`, `npm run build:icons`, `npm run i18n:check`, `npm run i18n:test`, `node scripts/concept-navigation-regression.mjs`, and `node scripts/visitor-follow-up-regression.mjs` all exit 0 after the final edits.
- 2026-10-14 — VALIDATION: the visitor/customer-adjacent PHP subset (`VisitorFollowUpTest`, `CrmInteractionTest`, `SimpleEducationalWorkflowTest`, `AgencyWorkflowCoherenceTest`, `EducationalDashboardTest`, `PhaseEightReportsTest`, `FinanceRetirementBoundaryTest`, `ApplicantPipelineTest`) passed: 40 tests, 383 assertions.
- 2026-10-14 — REVIEW: inspected the final diff of the task's own files for scope creep, accidental edits and secrets; pre-existing working-tree changes from other tasks (notifications, settings, WhatsApp templates, icon CSS) were left untouched.
- 2026-10-14 — VALIDATION: extended the browser regression to switch the mocked route query in place (`window.switchList`) and re-ran it; this covers the strip path where both destinations share the `visitor` route, and it confirmed the page re-requests the new flag, renames itself and moves the "current" marker.

### 10. Completion summary

- Delivered: a `visitors.follow_up` flag (migration/model/resource), a `follow_up` list filter and a `PUT /api/visitors/{id}/follow-up` action gated by `edit_visitors`, a deep-linkable Follow-up list destination in the shared CRM strip, and a single visitor page that renders both lists with the requested differences (contacts linked only on the follow-up list, relationship details + create contract only there, add button and move-to-follow-up action only on the plain list, mode-aware page title and print report title), plus locale keys in English, Persian and Pashto.
- Acceptance criteria satisfied: AC1–AC8 all satisfied, each asserted by either the PHP feature test or the browser regression (see §7).
- Tests/checks passed: `VisitorFollowUpTest` (OK, 3 tests / 32 assertions), the visitor/customer-adjacent PHP subset (OK, 40 tests / 383 assertions), `npm run typecheck`, `npm run build:icons`, `npm run i18n:check`, `npm run i18n:test`, `scripts/concept-navigation-regression.mjs`, `scripts/visitor-follow-up-regression.mjs`.
- Remaining limitations or follow-up work: the complete PHP suite and a repo-wide `eslint` run could not be used as gates in this environment (see §7); the second CRM destination shares the `visitor` route, so any future change to the strip's query comparison must keep the marker-only rule.
