# Contracts, Tasks, Printing & Seed Data Revamp — Implementation Plan

**Created:** 2026-10-10
**Owner:** Engineering
**Status:** Planning complete — implementation not started
**Progress log:** see [§9 Progress tracker](#9-progress-tracker). Update the tracker + log after **every** sub-task completion.

---

## 0. Scope, decisions and assumptions

Confirmed with the requester before planning:

| # | Decision | Choice |
|---|----------|--------|
| 1 | HR → Employment Contract (`employee_contracts.vue`) | **No change** — already clean. |
| 2 | Academy data model | **Keep current contract creation.** An Academy `ContractTemplate` stays in the DB but is **hidden from the Templates & Policies picker** (ensured/ seeded so generation cannot break). |
| 3 | Academy placement in nav | **Keep `Academy` under Contracts**; the page becomes an academy records list with a per-record slip print action. |
| 4 | Test Offer versioning | **Tests replace Country as the version key** (Test + Language). |
| 5 | "Responsible employee" wording | Rename **all** responsible-employee labels to *Counselor*. |
| 6 | KPI removal | Remove the metric cards on every data-table page, **keep the plain page title/description**. Reports & dashboards are exempt. |
| 7 | Seeded boards | `Private Contracts`, `Scholarship`, `Test Offer`, `Academy`, `Personal` — `Personal` is the default board for manually created tasks. |

Deliverable for this document: a working, verifiable implementation of all items, with this file updated as the running record.

### Glossary of relevant code

- Print engine: `resources/ts/utils/printDocument.ts`, `resources/ts/utils/printReceipt.ts`
- List print body: `resources/ts/components/PrintableListReport.vue`
- KPI component: `resources/ts/components/DataTablePageKpis.vue`
- Contract list pages: `resources/ts/pages/service-contracts.vue` (private / scholarship / test offer / academy), `contract_list.vue`, `cancelled_contracts.vue`, `other_contracts.vue`, `employee_contracts.vue`
- Template + policy admin: `resources/ts/pages/contract/policies.vue`, `contract/policy_library.vue`
- Contracts backend: `app/Http/Controllers/ContractController.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`, `app/Models/ContractTemplateVersion.php`
- Tasks backend: `app/Services/ContractTaskService.php`, `app/Services/ApplicantTaskService.php`, `app/Http/Controllers/TaskController.php`, `app/Services/EducationalWorkflowNotificationService.php`
- Task UI: `resources/ts/components/task-manager/TaskAppShell.vue`, `resources/ts/stores/useTaskStore.ts`
- i18n (frontend): `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` (backend `lang/*.json` has no affected keys)

---

## 1. Workstream A — Printing fixes

### A1. Data-table prints have no margin

**Symptom.** Printing any data-table page (income, expenses, contracts, staff, asset, salaries, visitors, applicants…) produces a page with no margin.

**Root cause (verified by reading the code).**
`printDocument` clones the hidden `.list-print-report` into `#app-print-host` and then applies, with `!important`:

```css
body.printing-isolated-document #app-print-host > * { display: block !important; margin: 0 !important; }
```

`#app-print-host > *` is the cloned `.list-print-report`. An ID selector with `!important` beats the report's own `body.printing-isolated-document .list-print-report { margin: 8mm }` (class selectors, no `!important`), so the report's inner margin is destroyed. The only remaining spacing is the injected `@page { margin: 12mm }`, which several browsers/print dialogs do not honour reliably once the whole body is replaced — so the practical result is a flush, margin-less page. The stale `resources/styles/styles.min.css` also carries an old `body[class*=printing-table-]` print block, but it is **not imported** (`main.ts` imports `styles.scss`), so it is not the active cause — remove the dead rules to avoid future confusion.

**Changes.**
1. `resources/ts/utils/printDocument.ts` — stop clobbering the cloned content's margin. Either:
   - drop `margin: 0 !important` from the `#app-print-host > *` rule and instead reset only box-model noise (`padding`/`border`) on the host, **or**
   - move the page inset onto the host itself: `#app-print-host { padding: 0; margin: 0 }` and let `.list-print-report` keep its own `8mm` margin.
   Keep `position: static` for the isolated copy. Ensure the last-injected `@page` rule still wins.
2. `resources/ts/components/PrintableListReport.vue` — make the report's print margin explicit and non-losing, e.g. `margin: 8mm !important` (namespaced under `body.printing-isolated-document`), and confirm logo/table spacing still works.
3. `resources/styles/styles.min.css` — delete the stale `@media print { body[class*=printing-table-] … }` block (and the `@media screen` compat rules) so only the namespaced `printDocument` rules remain. Regenerate from `styles.scss` if the build does that.
4. Audit the sibling document flows (`printing-table-*`, `printing-timesheet-template`, `printing-payslip-document`, `printing-contract-document`) for the same `margin: 0 !important` interaction; contracts/payslips use `margin: 0` deliberately, so scope any change to the list-report path.

**Acceptance criteria.**
- Every `printing-table-*` print shows a consistent margin on all four sides.
- Verified with a real browser/PDF (Playwright `page.pdf()` against a running dev server) for at least: income, expenses, contracts (service list), staff, asset.
- `scripts/receipt-print-regression.mjs` still passes; extend it (or add a sibling script) to assert the produced HTML/CSS keeps the report margin.

### A2. Icon-only buttons render blank when they contain a tooltip

**Symptom.** On the contract pages the print button (and a few others) shows no icon.

**Root cause (verified in `node_modules/vuetify/lib/components/VBtn/VBtn.js:205`).**
Vuetify renders the `icon` prop as a `<VIcon>` **only when there is no default slot**:
`!slots.default && hasIcon ? <VIcon :icon="icon"/> : <span>{slots.default()}</span>`.
A `<VTooltip activator="parent">` is a default-slot child, so the icon prop is suppressed and the button renders an invisible tooltip only. Confirmed offenders (`icon=` + tooltip-only child, no explicit `<VIcon>`):

- `resources/ts/pages/service-contracts.vue`: **199** (print list), **208** (print contract), **209** (extend), **215** (close)
- `resources/ts/pages/all_customers.vue`: **101** (print)
- `resources/ts/pages/visitor.vue`: **391** (create contract)

**Changes.**
For each offender, render an explicit icon child and keep the tooltip, i.e. `<VIcon icon="tabler-printer" /><VTooltip activator="parent">…</VTooltip>` (the pattern already used in `contract_list.vue`). Do **not** remove the tooltips.
Optionally add an ESLint/`rg` guard note; a scripted check (like the one used during planning) can be added to `scripts/` to fail CI on `VBtn icon="…"` + tooltip-only child.

**Acceptance criteria.**
- Print/extend/close buttons on all four contract pages show their icons in light/dark and LTR/RTL.
- `npm run build:icons` produces no new "used but unavailable" warnings.
- Manual browser check on the contracts pages and on `all-customers` + `visitor`.

---

## 2. Workstream B — Data-table page chrome

### B1. Remove in-page KPIs from data-table pages (reports/dashboards exempt)

`DataTablePageKpis.vue` renders a heading **and** a 4-card metric row. It is used by 13 list pages:

`pages/staff_salary.vue`, `pages/staff_list.vue`, `pages/contract/policy_library.vue`, `pages/contract/policies.vue`, `pages/setup/users.vue`, `pages/setup/positions.vue`, `pages/asset.vue`, `pages/setup/departments.vue`, `pages/options_hr.vue`, `pages/options_finance.vue`, `pages/options_contract.vue`, `pages/recurring-events.vue`, `pages/paid_time_offs.vue`.

**Changes.**
1. `resources/ts/components/DataTablePageKpis.vue` — keep the `data-table-page-summary__heading` (title + description + avatar), remove the `<VRow>` metric cards and all KPI computation that only feeds them (`metrics`, tone maps, currency/amount helpers, `total/healthy/needsAttention/groups`…). Keep the component name/props so the 13 call sites don't change (minimal diff). Rename it in a follow-up commit only if desired.
2. Remove the now-unused `.data-table-kpi*`, `.data-table-kpis` CSS from `resources/styles/styles.scss`.
3. Verify no other data-table page renders its own KPI cards. Audit for `.metric-tile`, `.data-table-kpi`, `.education-metric`. **Do not touch** `pages/reports.vue`, `pages/educational-reports.vue`, `pages/home.vue`, `pages/educational-dashboard.vue`, `pages/hr.vue`, `pages/finance.vue` (dashboards/reports).
4. Keep the `@media print { .data-table-page-summary { display: none } }` rule so the printed list still omits the heading.

**Acceptance criteria.**
- All 13 pages show only the title/description; no metric cards anywhere.
- Reports and dashboards are unchanged.
- `npm run typecheck` + `npm run i18n:check` pass (no orphaned keys from the removed card copy).

### B2. Make Income & Expenses headers coherent with other data-table pages

`pages/income.vue` uses a colored "command deck" (`.income-command-deck`) with hero copy, a print/settings/service-payment/add button cluster, and 4 `.metric-tile` KPIs. `pages/expenses.vue` follows a similar pattern.

**Changes.**
1. In `income.vue` and `expenses.vue`, delete the colored page-head block and its metric tiles.
2. Replace it with the same plain heading the other list pages use (title + description), and move the actions (`print`, `Record Income`/`Add Expense`, plus the income-only `Payment overrides` and `Service payments` actions) into an ordinary toolbar row above the filters — no colored background.
3. Remove the now-dead hero/metric CSS from both files (`.income-command-deck`, `.metric-tile`, `hero-*`, etc.).
4. Keep the search + `DataTableFilterMenu` block and the table card exactly as they are.

**Acceptance criteria.**
- Income and Expenses visually match the other list pages (plain head, actions toolbar, filters, table).
- `printTable` still prints the list with correct margins (depends on A1).
- Light/dark + LTR/RTL checked; no leftover unused imports/styles.

---

## 3. Workstream C — Contract templates & services

### C1. Test Offer: Tests replace Country as the version key (keep language)

Contract-template versions are currently unique/matched on `(template, country, language)`. Test-offer has no country, so test-offer versions cannot be separated per test.

**Backend.**
1. New migration `xxxx_add_test_id_to_contract_template_versions.php`:
   - `$table->foreignId('test_id')->nullable()->constrained('tests')->nullOnDelete();` (placed after `country_id`).
   - Drop `ctv_template_country_language_version_unique` and the `ctv_country_language_current_index`; recreate uniqueness/indexes including `test_id`, e.g. unique `(contract_template_id, country_id, test_id, language_id, version_number)`.
2. `app/Models/ContractTemplateVersion.php` — add `test_id` to `$fillable`, add `test()` relation, and include `test_id` in `scopeKey()` so `current_scope_key` uniqueness covers tests.
3. Version CRUD + activation endpoints (in `ContractTemplateController` / request classes): accept and validate `test_id` (`exists:tests,id`, active). Require `test_id` when the parent template's `service === 'test_offer'`, and forbid/ignore `country_id` for test-offer versions. Backfill `current_scope_key` when activating.
4. `app/Services/Contracts/ContractGenerationService.php`:
   - Keep `$countryId = null` for `test_offer`.
   - For `test_offer`, resolve `$testId = $applicant->testDetail?->test_id` (load the relation) and match the version on `test_id` + `language_id` + `status = active` + `is_current`; abort with a clear `business.builtin_contract_template_not_ready` (or a new test-specific key) when no version matches.
   - Keep `contract_country_id = null` for test-offer.
5. `app/Http/Resources/ContractTemplateResource.php` (and any version resource) — include `test` (`id`, `name`).
6. Seed/ensure at least one Test exists (`EducationalMasterDataTest` catalog) so the picker is not empty; add to `EducationalReferenceSeeder` if missing.

**Frontend.**
7. `resources/ts/api/contractTemplate.ts` — add `test_id` to version types/payloads and a `GetTests` source via `MasterDataApi.list('tests')`.
8. `resources/ts/pages/contract/policies.vue`:
   - When the edited template's `service === 'test_offer'`, the version form shows a **Test** selector (instead of, and disabling, Country); version list/filters show Test.
   - When `service !== 'test_offer'`, keep Country behaviour and the existing language dimension.
   - Persist `test_id` on create/update/activate.
9. `resources/ts/pages/service-contracts.vue`:
   - `matchingVersion` for `test_offer`: match `version.test_id === form.test_id` (and language), instead of requiring `country_id === null`.
   - Ensure `form.test_id` is collected (already via `ApplicantServiceFields` for test-offer) and sent in the create payload (`test_id` is already in `serviceContractApplicantFields`).
   - Show the resolved Test in the read-only "Contract version" summary.
10. Confirm `ApplicantServiceFields.vue` still omits Country for test offer (it already does) and that no country field is required by `StoreServiceContractRequest` for test-offer.

**Acceptance criteria.**
- Two different Tests can each hold an active, current version for the same language.
- Creating a test-offer contract for a test picks the version matching that Test + language; a missing version is a clear 422.
- Existing scholarship/private (country) and academy (no country) versioning is unchanged.
- Backend tests: extend `ContractTemplateVersionTest`, `VersionedContractGenerationTest`, `EducationalMasterDataTest`.

### C2. Academy: remove from Templates & Policies, ensure a hidden Academy template, add an Academy slip

**Decisions.** Academy keeps creating a normal Contract (so the flow and payment tracking are unchanged), but Academy is no longer offered in the Templates & Policies service selector, Academy templates are hidden from that list, and an **Academy slip** (receipt-like, A5) is printable per academy record.

**Changes.**
1. Remove `'academy'` from the service arrays used by the admin pages:
   - `resources/ts/pages/contract/policies.vue` (`builtInServices`).
   - `resources/ts/pages/contract/policy_library.vue` (`serviceItems`).
   - Keep deleting/editing existing academy rows impossible-but-inert; hide `service === 'academy'` templates and versions from the visible lists so admins see only private/scholarship/test-offer.
2. Ensure an Academy `ContractTemplate` + at least one active/current version exists so `ContractGenerationService` never aborts (idempotent seeder `AcademyContractTemplateSeeder`, or extend `EducationalReferenceSeeder`): template `service = 'academy'`, a `customer` contract type, one version per active language with `country_id = null` and a minimal receipt-like body. Register it in `DatabaseSeeder`.
3. Academy slip document:
   - New `resources/ts/pages/templates/academy_slip_template.vue`, modelled closely on `resources/ts/pages/templates/receipt_template.vue` (letterhead, firm branding, applicant identity, academy program/time/duration/class/teachers, fee/discount/paid/balance, serial/reference numbers, signature area). A5, `printDocument`-based, `@page` injected only for that print job (never in the SFC stylesheet).
   - New `resources/ts/components/academy/AcademySlipPrinter.vue` (mirror of `components/income/ReceiptPrinter.vue`) with paper-size selector + copies.
   - Backend: an academy-slip data endpoint, e.g. `GET /academy-records/{applicantContractOrContract}/slip`, returning company branding/letterhead + academy detail + fee/payment state, reusing `ReceiptApi`/letterhead conventions and the double-`data` wrapper. Include `signature`/letterhead exactly like receipts.
   - `resources/ts/api/academySlip.ts` typed contract (reference `api/incomes.ts` + `api/receipts.ts`).
4. `resources/ts/pages/service-contracts.vue`: when `service === 'academy'`, render an **Academy slip** print action per row (and in the row actions) that opens `AcademySlipPrinter` for that record. Keep the normal contract print action.
5. i18n: add slip labels to all three locales (`en`, `fa-AF`, `ps-AF`).

**Acceptance criteria.**
- Academy still creates a contract; generation does not fail on a fresh install (seeder guarantees the hidden template).
- Academy no longer appears in Options → Contracts & Policies (templates or policy service filter).
- Printing an Academy slip for an individual academy record produces a clean A5 receipt-like document with margins and branding, in light/dark-agnostic print and LTR/RTL.
- New backend test asserting the hidden Academy template exists and academy generation succeeds.

### C3. Contracts pages: "Responsible employee" → "Counselor", auto-select the counselor

**Changes.**
1. i18n (all three locales `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`): change the value of the `serviceContracts.responsibleEmployee` **and** `educational.responsibleEmployee` keys to *Counselor* (Persian/Dari equivalents). Keep keys unchanged to avoid code churn.
2. `resources/ts/pages/service-contracts.vue`:
   - Rename the field semantics to counselor (label already flows from the key).
   - Prefer a **counselor-scoped** employee list for this field. Either add `counselor_employees` to `ContractController::serviceOptions` (via `AgencyStaffAssignment::query('counselor')`, mirroring `admission_employees`) and bind it to the field, or keep `employees` and rely on the label only — pick counselor-scoped for correctness and note the backend change.
   - In `create()`, after options load: if the current user is a counselor (`useCookie('userData').value.roles` contains `counselor`/`senior_counselor`) and their `employee.id` is selectable, auto-set `form.responsible_employee_id` to that employee.
3. Backend confirmation: `StoreServiceContractRequest` already validates `responsible_employee_id`; if we switch to counselor-scoped options, validate against `AgencyStaffAssignment::rules('counselor')`. `ContractGenerationService::createApplicant` already defaults `responsible_employee_id` to the actor's employee when the actor lacks `assign_applicant_employee` — keep that as the server-side safety net.
4. Audit the other `responsible_employee` label consumers (`applicants.vue`, `applicant-case.vue`, `applicant-pipeline.vue`, `educational-dashboard.vue`) for wording consistency after the rename.

**Acceptance criteria.**
- On each of the four service-contract pages the field reads **Counselor** and lists counselors.
- A user with the counselor role sees their own name preselected.
- `npm run i18n:check` / `i18n:test` pass; no key renames leaked.

---

## 4. Workstream D — Tasks & navigation

### D1. Remove Gantt and Calendar from the task navigation

Everything lives in one shell: `resources/ts/components/task-manager/TaskAppShell.vue` builds the tab list (`kanban`, `calendar`, `gantt`, `todos`, `settings`) and switches components; `resources/ts/stores/useTaskStore.ts` holds `activeView: 'kanban' | 'calendar' | 'gantt' | 'todos' | 'settings'` plus `ganttTasks` / `calendarEvents` computeds.

**Changes.**
1. `TaskAppShell.vue`: remove the `calendar` and `gantt` entries from `tabs`, and remove the corresponding branches from `currentComponent`.
2. `resources/ts/stores/useTaskStore.ts`: narrow `activeView` to `'kanban' | 'todos' | 'settings'`, drop the `gantt`/`calendar` handling in `setView`, and remove the now-unused `ganttTasks`/`calendarEvents` computeds + imports.
3. Delete or orphan safely: `views/GanttView.vue`, `views/CalendarView.vue`, `components/FrappeGanttWrapper.vue`, the FullCalendar/Gantt deps in `vite.config.ts`/`auto-imports.d.ts`/`components.d.ts` and `utils/taskTransformers.ts` exports (`toCalendarFormat`, `toFrappeGanttFormat`) **only after** confirming nothing else imports them. Remove `frappe-gantt` + FullCalendar packages if fully unused.
4. i18n: leave the existing `calendarview`/`ganttview` keys in place (harmless) or remove them in a cleanup pass; do not break `i18n:check`.

**Acceptance criteria.**
- Tasks page shows only Kanban, Todo matrix, Settings.
- No dangling imports; `npm run typecheck` and `npm run build` pass.
- `taskTransformers`' remaining users still compile.

### D2. Notify employees when a task is created with assignees

`TaskController::store` already calls `EducationalWorkflowNotificationService::taskAssigned(...)`, but tasks created **inside services** do not notify:
- `ContractTaskService::ensureForApplicantContract` (contract delivery task),
- `ApplicantTaskService::ensureForStage` (stage work),
- `RecurringEvents\RecurringEventService` (`$task->users()->sync(...)`).

**Changes.**
1. Inject `EducationalWorkflowNotificationService` into `ContractTaskService`, `ApplicantTaskService` and the recurring-event service, and call `taskAssigned($task, $assigneeUserIds, $actorId)` after assignment. `taskAssigned` is already idempotent per `(task, user)` via `notifyOnce`, so re-saves won't double-notify.
2. Make sure the notification title/message/link are sensible for service-created tasks; reuse the existing `task.assigned` translation key rather than inventing a new one, or add a dedicated key if a distinct wording is wanted.
3. Consider centralising on a single `TaskAssignmentNotifier` so all creation paths notify identically (preferred, avoids duplication); both controllers/services call it.

**Acceptance criteria.**
- Creating a task (via API or a service) with assignees creates one notification per assignee (never the actor).
- Contract-creation tasks and stage tasks notify their assignees.
- New backend test: contract creation → task → notification row exists for the assignee(s).

### D3. Contract creation auto-creates a task assigned to the Admission employee **and** the Counselor

`ContractTaskService::ensureForApplicantContract` already creates one deliverable task per contract line, but assigns only `responsibleEmployee?->user_id ?? actor`. It must also include the admission employee.

**Changes.**
1. `app/Services/ContractTaskService.php`:
   - Compute assignees from **both** `$applicant->responsibleEmployee?->user_id` (Counselor) and `$applicant->admissionEmployee?->user_id` (Admission), plus the actor as a last-resort fallback when neither exists.
   - `loadMissing` the new relations; filter out null/deleted users; de-duplicate.
   - Assign all to the task (`$task->users()->sync($ids)`) and to the board (`syncWithoutDetaching`).
   - Keep idempotency (one open task per contract line) intact.
2. Set the task's board to the service board (see E1): resolve/create the board by service name (`Private Contracts` / `Scholarship` / `Test Offer` / `Academy`) instead of the single hard-coded `Contracts` board, so work lands where the team expects. Keep `Personal` for tasks with no service.
3. Notify the new assignees (covered by D2).
4. Confirm `Applicant` loads `admissionEmployee` (relation exists at `app/Models/Applicant.php:92`).

**Acceptance criteria.**
- Creating a contract with an admission employee and a counselor produces exactly one task per contract line, assigned to both, on the correct service board, with notifications.
- Re-saving/editing the contract does not duplicate the task or re-notify.
- Backend tests: extend `ContractTaskAssignmentTest` (+ `ApplicantTaskIntegrationTest`).

---

## 5. Workstream E — Seeders

### E1. Seed coherent Kanban boards

Today `BoardSeeder` creates `Backlog/To Do/In Progress/Review/Done` and `KanbanSeeder` creates another `To Do/In Progress/In Review/Done` — duplicated, meaningless boards.

**Changes.**
1. `database/seeders/BoardSeeder.php` — create exactly the working boards, idempotently (`updateOrCreate` on `title`), with stable `order`:
   `Private Contracts`, `Scholarship`, `Test Offer`, `Academy`, `Personal`.
2. Decide/implement where "Personal" is the default for manual task creation (frontend default board selection on the Tasks/Kanban "new task" flow; fall back to the first service board if a service board is expected).
3. `database/seeders/KanbanSeeder.php` — stop creating its own duplicate board set and its own random users; use the seeded service boards.
4. Add a small `Board::query()->where('title', …)` helper or constant list so `ContractTaskService` (D3) and seeders agree on board names (single source of truth, e.g. `App\Support\ServiceBoard::titleFor(string $service)`).

**Acceptance criteria.**
- `db:seed` produces exactly the five boards (no duplicates).
- Contract tasks land on the matching service board; manual tasks default to Personal.

### E2. Make seeded tasks and relations sensible

Today `TaskSeeder` gives every board 3–8 random factory tasks; `TaskUserSeeder` assigns random users with no relation to the work; `ContractSeeder` creates contracts with hard-coded counterparties; `EmployeeSeeder` creates 3 staff all with `position_id = 1`; `KanbanSeeder` invents 5 extra factory users.

**Changes.**
1. `EmployeeSeeder` — seed a small, purposeful staff set with real positions/roles, including at least one **counselor** and one **admission** employee (so `AgencyStaffAssignment` queries and the counselor auto-select actually work in demo data).
2. `TaskSeeder` — create tasks that match their board: e.g. on `Private Contracts`/`Scholarship`/`Test Offer`/`Academy` create contract-delivery tasks linked to real seeded applicants/contracts for that service; on `Personal` create general/admin tasks. Prefer deriving tasks from the same rules as `ContractTaskService` rather than random text.
3. `TaskUserSeeder` — assign each task to the **applicant's** counselor/admission users (matching the real business rule), not random users. Ensure every assignee is a board member.
4. `TaskTagSeeder`, `TaskDependencySeeder`, `CommentSeeder`, `SubtaskSeeder` — keep only relations that are coherent with the parent task (a subtask/comment belongs to a real assignee; dependencies only between tasks on the same board).
5. `ContractSeeder` — either remove it or rebuild it against the built-in services so seeded contracts have applicants/service lines, instead of random counterparties with no applicant.
6. `KanbanSeeder` — drop the 5 `User::factory` creations; rely on `EmployeeSeeder`'s real people. If it must add colour, create tasks on the seeded boards only.
7. Register any new/renamed seeders in `DatabaseSeeder` and keep the dependency order.

**Acceptance criteria.**
- After `db:seed`, every task is on a service/personal board, every assignee is a real seeded employee user, and contract tasks reference real applicants/contracts.
- No orphaned boards, no duplicate boards, no random factory users with no role.
- `ApplicantPortalDemoSeederTest` and any seeder-dependent tests still pass; add a seeder sanity test asserting board/task/assignee coherence.

---

## 6. Cross-cutting: translations

- All label changes go in **both** frontend locales (`resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`) and, only if the key exists, backend `lang/*.json`.
- New keys: Academy slip labels, counselor field labels, any new task-notification copy, test-version field labels.
- Keep keys stable; change values. Run `npm run i18n:check` and `npm run i18n:test`, and update the i18n runtime test fixtures if they assert exact strings.

---

## 7. Validation strategy

Run after every workstream, and at the end:

1. `npm run typecheck` (never bare `vue-tsc`/`tsc`).
2. `npm run i18n:check` + `npm run i18n:test`.
3. `npm run build:icons` and treat any "used but unavailable" warning as a bug.
4. `npm run build`.
5. Backend: targeted PHPUnit (`php artisan test --filter=...`) for the touched suites — `ContractTemplateVersionTest`, `VersionedContractGenerationTest`, `BuiltInContractGenerationTest`, `BuiltInContractTemplateTest`, `ContractTaskAssignmentTest`, `ApplicantTaskIntegrationTest`, `EducationalMasterDataTest`, `ServiceContractNavigationTest`, `AgencyWorkflowCoherenceTest`. Run `vendor/bin/pint` on changed PHP files.
6. Print verification with a real browser/PDF: start the dev server, use Playwright (`page.pdf({ format: 'A4' })`) to render each `printing-table-*` page and one Academy slip, and confirm the margins/paper size in the produced PDF. Keep `scripts/receipt-print-regression.mjs` green and extend `scripts/service-contract-regression.mjs` / `scripts/agency-document-regression.mjs` for the slip and the counselor field.
7. Manual matrix per UI change: light + dark theme, LTR + RTL, mobile/tablet/desktop.

---

## 8. Explicitly out of scope / unchanged

- HR → Employment Contract (`employee_contracts.vue`): confirmed clean — no work.
- Reports and dashboards keep their KPIs.
- Existing issued contract snapshots and receipt numbers stay untouched.
- No invoices/companies/commission/outsourcing resurrected.

---

## 9. Progress tracker

Legend: `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked

| ID | Sub-task | Status | Notes / commit |
|----|----------|--------|----------------|
| A1 | Fix data-table print margins (`printDocument` + `PrintableListReport`, drop stale print CSS) | [x] | `printDocument.ts`: page margin moved onto the isolated print host + `@page` emitted at top level; deleted dead `styles.min.css(.map)` |
| A2 | Fix blank icon-only buttons (contracts/all-customers/visitor tooltip buttons) | [x] | Added explicit `<VIcon>` to 6 `VBtn icon=…` + tooltip-only buttons (service-contracts ×4, all_customers, visitor); detector script reports 0 remaining |
| B1 | Remove KPI metric cards from all data-table pages (keep title); reports/dashboards untouched | [x] | `DataTablePageKpis.vue` now renders only the heading; removed `.data-table-kpi*`/`.data-table-kpis` CSS |
| B2 | Restyle Income & Expenses headers to match other list pages | [x] | Replaced the colored hero + metric tiles with a plain heading + actions toolbar in `income.vue`/`expenses.vue`; removed dead hero/metric code |
| C1 | Test Offer: Tests replace Country for template versions (keep language) | [x] | Migration `2026_10_10_000001_add_test_id_to_contract_template_versions`; version scope/uniqueness now includes `test_id`; controller/generation/resource + policies & service-contracts UI + seeded `tests` catalogue. Backend tests not run (no local MySQL) |
| C2 | Academy: hide from Templates & Policies, ensure hidden template, build + wire Academy slip | [x] | Academy removed from `policies.vue`/`policy_library.vue` service selectors and academy templates hidden from the admin list; `AcademyContractTemplateSeeder` ensures a hidden template + one active/current version per language (registered in `DatabaseSeeder`); new `AcademySlipService` + `GET contracts/{id}/academy-slip`; `api/academySlip.ts`, `pages/templates/academy_slip_template.vue` (A5/A4 receipt-like, `printDocument`), `components/academy/AcademySlipPrinter.vue`; wired as an Academy-slip action on academy rows; 29 i18n keys in all three locales. `typecheck`, `i18n:check`/`i18n:test`, `build:icons`, print/contract/agency regression scripts pass |
| C3 | Contracts: rename Responsible employee → Counselor; auto-select current counselor | [x] | All three locales renamed (`responsibleEmployee` → Counselor/مشاور); `ContractController::serviceOptions` returns `counselor_employees` (via `AgencyStaffAssignment`); `service-contracts.vue` binds the field to counselors and preselects the signed-in counselor from the `userData` cookie. `typecheck` + i18n checks pass |
| D1 | Remove Gantt + Calendar from task navigation | [ ] | |
| D2 | Notify assignees when tasks are created in services (not just the controller) | [ ] | |
| D3 | Contract creation auto-task assigned to Admission + Counselor, on the service board | [x] | `ContractTaskService` now assigns both `responsibleEmployee` (Counselor) and `admissionEmployee`, falling back to the actor only when the case has neither, and lands work on the service board via `ServiceBoard::titleFor()`. Assignee notifications are handled by D2 (still pending) |
| E1 | Seed the five coherent Kanban boards + default board rule | [x] | New `App\Support\ServiceBoard` is the single source of truth; `BoardSeeder` seeds exactly `Private Contracts`, `Scholarship`, `Test Offer`, `Academy`, `Personal`; `KanbanSeeder` seeds membership only (no duplicate boards/users); `ContractTaskService`/`ApplicantTaskService` resolve boards through it |
| E2 | Rebuild seeders so tasks/relations/applicants/employees are coherent | [x] | Rebuilt `PositionSeeder`, `EmployeeSeeder`, `EducationalReferenceSeeder`, `ApplicantSeeder`, `ContractSeeder`, `TaskSeeder`, `TaskUserSeeder`, `TagSeeder`, `TaskTagSeeder`, `TaskDependencySeeder`, `CommentSeeder`, `SubtaskSeeder`, `SalarySeeder`, `WorthyEmployeeSeeder`, `AttendanceSeeder`, `VisitorSeeder`, `CrmSeeder`, `AssetSeeder`, `NotificationSeeder`, `ApplicantPortalDemoSeeder`; reordered `DatabaseSeeder`. All PHP files pass `php -l`. Backend PHPUnit not run (no local MySQL) |
| V | Validation pass (typecheck, i18n, icons, build, PHPUnit, PDF print checks) | [~] | Done this session: `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test`, `npm run build:icons` (no unavailable-icon warnings), `node scripts/receipt-print-regression.mjs`, `node scripts/service-contract-regression.mjs`, `node scripts/agency-document-regression.mjs`, `php -l` + `vendor/bin/pint` on changed PHP. **Blocked in this environment:** `npm run build` fails because the Linux-native `@rollup/rollup-linux-x64-gnu` binary is missing (node_modules installed under Windows/pnpm); backend PHPUnit needs MySQL (unavailable); browser/PDF print verification needs Playwright (not installed) |

### Progress log

- **2026-10-10** — Plan reviewed, decisions confirmed with the requester, document created. No implementation started.
- **2026-10-10** — A1 done. Root cause: `printDocument` relied on `@page` margins, which the browser print dialog's margin setting overrides; list prints therefore had no margin. Fix: emit `@page` at the top level of the injected sheet and move the page margin onto the cloned `#app-print-host` as padding (`@page … margin: 0`), which the dialog cannot override. Also removed the unreferenced, stale `resources/styles/styles.min.css(.map)` that carried misleading legacy print rules.
- **2026-10-10** — C1 done. `contract_template_versions.test_id` added (nullable, FK `tests`); the (template, country|test, language, version) unique index and `current_scope_key` now include the test; `ContractTemplateVersionController` requires exactly one scope per template service (test for test-offer, country otherwise); `ContractGenerationService` matches test-offer versions by the applicant's test; resource + `policies.vue` (initial version, version dialog, filters, table) + `service-contracts.vue` matching all use the test dimension. Seeded IELTS/TOEFL/Duolingo tests. `npm run typecheck` passes; **backend PHPUnit could not run — no MySQL available in this environment**.
- **2026-10-10** — B1 done. `DataTablePageKpis` is now heading-only (props kept so all 13 call sites are untouched); removed the unused KPI CSS from `styles.scss`. Reports/dashboards keep their own metrics.
- **2026-10-10** — B2 done. Income and Expenses use a plain heading + action toolbar (print, income-only payment-override/service-payment actions, record button) instead of the colored command deck; removed the dead hero/metric tiles, their computeds and CSS. `npm run typecheck` passes.
- **2026-10-10** — A2 done. Root cause: Vuetify `VBtn` suppresses the `icon` prop when the button has a default slot, and a `<VTooltip activator="parent">` counts as one. Added explicit `<VIcon>` children (tooltips kept). `npm run typecheck` passes.
- **2026-10-10** — C3 done. `responsibleEmployee` renamed to *Counselor* (fa/ps too); `ContractController::serviceOptions` adds `counselor_employees` from `AgencyStaffAssignment::query('counselor')`; `service-contracts.vue` lists counselors and preselects the signed-in counselor via the `userData` cookie. `npm run typecheck`, `i18n:check`, `i18n:test` pass.
- **2026-10-10** — E1/E2 done (seeder rebuild, the requester's focus). Added `App\Support\ServiceBoard` as the single source of truth for board titles. `BoardSeeder` seeds exactly the five working boards; `KanbanSeeder` now only wires board membership from real staff (no invented boards or factory users). `PositionSeeder` gained agency positions with `system_key`s; `EmployeeSeeder` seeds six interconnected staff (manager, senior counselor, counselor, admission, finance, document) with roles, positions and a reporting line; `EducationalReferenceSeeder` now also seeds subjects, skills, academy programs/times/durations/teachers; `ApplicantSeeder` seeds one full case per built-in service (identity + detail row + subjects/skills/teachers + counselor/admission + stage); `ContractSeeder` issues one built-in contract + service line per applicant (with paid/partial/unpaid states); `TaskSeeder` mirrors `ContractTaskService` to create service-board delivery tasks plus operational Personal tasks; `TaskUserSeeder` assigns the applicant's counselor and admission employee; `TagSeeder`/`TaskTagSeeder`/`TaskDependencySeeder` are now agency-coherent; `CommentSeeder`/`SubtaskSeeder` are authored by real assignees; attendance/salary/bonus/visitor/CRM/asset/notification seeders use the seeded staff and built-in services; `ApplicantPortalDemoSeeder` assigns the seeded counselor/admission. `DatabaseSeeder` reordered so reference data precedes the records that depend on it. Also moved `ContractTaskService`/`ApplicantTaskService` onto `ServiceBoard` and made contract tasks assign both the counselor and the admission employee (D3). All seeders and touched services pass `php -l`; `npm run typecheck` + `npm run build:icons` (no unavailable-icon warnings) pass. **Backend PHPUnit still cannot run here (no MySQL).**
- **2026-10-10** — C2 done. Academy is no longer offered in Templates & Policies (`policies.vue` service array + filtered templates; `policy_library.vue` service items), and `AcademyContractTemplateSeeder` now ensures a hidden academy template with one active, current version per active language (static body, so rendering can never fail on unresolved placeholders), registered in `DatabaseSeeder`. Added the Academy slip: `AcademySlipService` builds the payload, `GET contracts/{id}/academy-slip` (view_contracts) returns it, `api/academySlip.ts` types it, `pages/templates/academy_slip_template.vue` renders a receipt-like A5/A4 slip through `printDocument` (prints via a `printing-academy-slip` body class, no `@page` in the SFC), and `components/academy/AcademySlipPrinter.vue` provides the preview/paper-size/copies dialog. `service-contracts.vue` shows an Academy-slip action on academy rows. 29 `documents.academySlip.*` keys added to en/fa-AF/ps-AF. `npm run typecheck`, `i18n:check`, `i18n:test`, `build:icons`, and the receipt/service-contract/agency-document regression scripts all pass. `npm run build` is blocked by a missing native rollup binary in this environment (not a code issue).
