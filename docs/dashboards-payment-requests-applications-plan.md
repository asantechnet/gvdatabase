# Dashboards, Payment Requests, Applications & Contract Fixes — Plan & Progress

**Created:** 2026-10-12
**Owner:** Engineering
**Status:** Complete
**Source request:** `docs/Educational New Comments++.md`

Single source of truth for the second round of comments. The **Progress tracker**
(§7) and **Progress log** (§8) are updated after every sub-task.

---

## 0. Confirmed decisions (answered by the requester)

| Topic | Decision |
|-------|----------|
| Loans → Payment requests | User-facing rename + a new `/payment-requests` route; keep the `PendingTransaction` model and the legacy `/loans` alias. |
| Payment-request print | **Two distinct slip layouts** — income slip vs expense/payment voucher. |
| Other contracts counterparty | **No counterparty at all** — it is a free-form contract template the user completes by hand. |
| Dashboards/reports | Work on the two existing pages: `/visa-reports` (educational-focused) and `/reports` (global) and give them **focused sections**: Educational/CRM (applicants, pipeline, contracts, tasks, documents), Finance (income, expenses, salaries, commissions, outstanding balances), HRM (staff, attendance, leave, payroll, performance). |
| Applications | Rename route/labels to **Applications**; keep `/applicants` as a redirect to `/applications`. |
| Academy data page | Its own page under the contracts submenu with **times, durations, teachers, classes**. |
| Applicant name → CRM | **Both**: inline contact menu + relationship on both pages, and a deep link that opens the target applicant's contact menu. |

---

## 1. Requested changes (verbatim intent)

1. Other contracts do not have any counterparty.
2. Personal Kanban boards are private per employee; a super admin can currently see every personal board.
3. Academy applications do not need an associated employee name.
4. The dashboard must include the new task/contract data. Visa reports and the global report must be refocused on educational/finance/HRM/CRM.
5. Duplicated fields in service contracts must move to Applicants — especially fields that describe the Applicant itself.
6. Extract the academy data (times, durations, teachers, classes) from Options → Contracts → Educational data into its own page under the contracts submenu, matching the existing design.
7. Rename the "all applicants" page under `/applicants` fully to **Applications**.
8. Applications need a payment status: paid / partially paid / unpaid.
9. Visa case page and applications page: link the applicant name to CRM, opening that applicant's phone contact menu (plus inline relationship/quick contact).
10. Loans become **payment requests**: an admission/counselor can send one; a finance manager approves and prints it; the print depends on the income/expense state.

---

## 2. Workstreams

### W1 — Personal board privacy (comment 2)
Already fixed in the working tree: `Board::scopeVisibleTo` and `TaskAccess::canViewBoard` no longer widen a personal board for super admins, and `PersonalBoardIsolationTest` covers it. **Verify only.**

### W2 — Academy applications: no associated employee name (comment 3)
Academy applications/contracts must not require or display a responsible/admission employee name. Hide and skip the employee field for `service === 'academy'` on the applicant create/edit and the academy contract form; keep it optional server-side.

### W3 — Other contracts: no counterparty (comment 1)
Remove the counterparty inputs, column, filter and print column from the **other** audience; store an empty counterparty and do not require it. Employment contracts keep their employee-derived counterparty.

### W4 — Academy data page (comment 6)
Extract `times`, `durations`, `teachers`, `classes` from the mixed Educational data page into a dedicated page under the contracts submenu, reusing the master-data table/CRUD design.

### W5 — Applications rename + payment status (comments 7, 8)
Rename the route/labels to Applications (`/applications`, `/applicants` redirect). Add a derived payment status (unpaid / partially paid / paid) per application, shown as a chip and available as a filter.

### W6 — Applicant name → CRM contact (comment 9)
Reuse `ContactActionMenu` + `CrmRelationshipDetails` inline on both the applications page and the visa case page, and support a deep link (`?contact=<id>`) that auto-opens the contact menu.

### W7 — Payment requests (comment 10)
Rename the Loans workspace to Payment requests (UI + `/payment-requests` route + nav/permissions alias), allow admission/counselor submission, finance-manager approval, and **two print layouts** driven by income/expense.

### W8 — Dashboard & reports revamp (comment 4)
Refocus `/reports` (global) and `/visa-reports` (educational) with Educational/CRM, Finance and HRM sections drawing on the new contract/task data; extend the agency overview with tasks/contracts.

### W9 — Service-contract duplicated fields (comment 5) — **dropped**
Move applicant-identity fields out of the service-contract form and into the Applicant record; the contract form selects/creates the applicant instead of re-collecting identity.

> **Dropped on 2026-10-12 at the requester's direction.** The identity fields stay
> where they are; no service-contract form is reshaped. Recorded here only so the
> comment in `docs/Educational New Comments++.md` is not re-triaged as unfinished
> work.

---

## 2b. Document numbering (follow-up request)

Payment requests and every other document generated by this round use the `PR-`
series instead of the old `LN-` (loans) prefix: `LoanReceiptService::serial()`,
`tests/Feature/PendingTransactionTest.php` and
`scripts/loan-receipt-print-regression.mjs` were updated together so the number
stays stable for the life of the record.

---

## 3. Progress tracker

Legend: `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked

| ID | Sub-task | Status | Notes |
|----|----------|--------|-------|
| W1 | Personal board privacy verify | [x] | `Board::scopeVisibleTo` + `TaskAccess::canViewBoard`; `PersonalBoardIsolationTest` 2/2 |
| W2 | Academy applications: no employee name | [x] | Server clears both employee ids for academy; field hidden in applicant create/edit + academy contract form; table shows `—` |
| W3 | Other contracts: no counterparty | [x] | Column/filter/print party + form fields gone for the `other` audience; server stores an empty counterparty and no longer requires one |
| W4 | Academy data page extraction | [x] | New `pages/academy-data.vue` (`/contracts/academy-data`) with times/durations/teachers/classes; removed from the educational data page; nav entry under Options → Contracts (relocated there from the Contracts group, see the W4b log entry) |
| W5 | Applications rename + payment status | [x] | `/applications` + `/applicants` query-preserving redirect; payment-status chip + filter live |
| W6 | Applicant name → CRM contact menu | [x] | Inline phone/email menu + relationship on Applications and visa case; name deep-links to CRM applicants with `?contact=<id>` auto-opening the phone menu |
| W7 | Payment requests + two print layouts | [x] | `/payment-requests` (canonical API + route) with `loans`/`pending-transactions` aliases; print gated on approval; income prints the income slip, expense/salary prints a new payment voucher |
| W8 | Dashboard & reports revamp | [x] | Agency overview gains a task/contract workload section; `/reports` families split into Educational/CRM/HRM/Finance domains shown as chips; `/visa-reports` grouped behind an Educational & CRM / Finance / HRM domain switch |
| W9 | Service-contract duplicated fields → Applicants | [x] | **Deliberately dropped** at the requester's direction — the identity fields stay where they are |

---

## 4. Progress log

- **2026-10-12** — Recon + decisions. Reviewed the affected surfaces and confirmed the seven decisions in §0. The personal-board privacy fix is already present in the working tree (with tests); everything else is to implement.
- **2026-10-12 — W1 verified.** `PersonalBoardIsolationTest` 2/2 green against SQLite.
- **2026-10-12 — W5 done.** `resources/ts/plugins/1.router/routes.ts`: the list route is now `/applications` (name `applications`); `/applicants` is a query-preserving redirect and `/applications/:id` redirects to the case route, so existing deep links keep working. Nav (`navigation/horizontal/applicants.ts`) and the page header/tabs read "Applications"/"All applications". Payment status is a derived value (`Applicant::overallPaymentStatus()`) surfaced by `ApplicantResource`, shown as a chip and filterable. Keys added to en/fa-AF/ps-AF.
- **2026-10-12 — W6 done.** `ContactActionMenu` gained an `autoOpen` prop. The applications page shows the inline phone menu plus a CRM relationship dialog and now links the applicant name to CRM → Applicants with `?contact=<id>`; the visa case page does the same (name link + phone/email menus + relationship), and `all_customers.vue` auto-opens the matching phone menu and highlights the row on that deep link.
- **2026-10-12 — W3 done.** `contract_list.vue` drops the counterparty column, filter, search match and print party block for the `other` audience; `ContractForm.vue` hides the counterparty identity fields and never fills them from the selected record for that audience (`CounterpartyCard` gained a `titleKey` override); `ContractController::documentAttributes()` stores an empty counterparty for the `other` slug and no longer requires one. 94/94 contract tests green.
- **2026-10-12 — W8 done.** `EducationalDashboardController` gained a `work` section (open/due-today/overdue/unassigned/completed tasks by assignee scope, plus contract counts by status, new-in-period and service lines) and the agency overview renders two new cards with links into the task board and contracts. On the global studio, `ReportService` families are now `financial`, `payroll`, `attendance`, `educational`, `crm` (the old `operations` blob split by focus) with a `CATEGORIES` map, and the page shows each family's domain chip. The visa reports page adds an Educational & CRM / Finance / HRM switch that filters the report tabs, with a watcher that only reloads when the report itself changes. Also repaired two stale tests left behind by the Restructure 2.0 commit (a retired marketers endpoint and a dashboard permission expectation that predates the `view_agency_overview` gate).
- **2026-10-12 — W4 done.** Times/durations/teachers/classes moved off `educational-master-data.vue` into the new `academy-data.vue` page (tabs + table + dialog, same design), routed at `/contracts/academy-data` and added to the Contracts nav submenu. The shared `educational-master-data` API and its backend validation are unchanged, so both pages keep one contract.
- **2026-10-12 — W7 done.** `routes/api.php` registers the workflow once and loops it over `payment-requests` (canonical) plus the `loans` and `pending-transactions` aliases; the frontend contract and RBAC mirror moved to `/payment-requests`. The workspace is now "Payment requests" (`/payment-requests`, with `/pending-transactions` and `/loans` redirects), the print action appears only once a request is approved/posted, and `LoanReceiptService` reports the payee. Printing has **two layouts** in the shared A5 template: money in keeps the income receipt, money out (expense/salary) renders a new payment voucher with payee, approval block and three signature lines. `scripts/loan-receipt-print-regression.mjs` was extended to render and assert both layouts in all three locales. Also fixed a Round-1 leftover: `FinanceRetirementBoundaryTest` still expected the commission workspace to 404.
- **2026-10-12 — Final verification.** `npm run typecheck` clean; `npm run i18n:check` (3025 used keys, no missing) and `npm run i18n:test` (4381 keys × 3 locales) pass; `npm run build:icons` bundles 292 references with no unresolved-token warnings; `vendor/bin/pint --test` clean on every file this round touched; the three browser/Node regression scripts pass; and the **full PHPUnit suite is green: 359 tests, 3117 assertions**.
- **2026-10-12 — Stale tests repaired (pre-existing, none caused by this round).** Four failures predated the round and only surfaced because they were never run as a set:
  - `GenericAgreementTest` — two assertions still encoded the *old* other-contract rule (the counterparty was derived from the linked applicant and one of the two was required). Both now assert the new rule: no counterparty is stored or required, and swapping the record link leaves the party columns empty.
  - `CurrencyRateServiceTest::test_income_and_expense_snapshots_cannot_override_the_base_rate` — posted the income-only type `Service` to `/api/expenses`, which the `finance_types` defaults reject. Each portal now posts one of its own valid types.
  - `PositionManagementTest` — asserted the updated position sorted first, but a migration seeds a position titled "Admission" that alphabetically precedes it; the assertion now checks the update is listed (and an unused import was dropped).
  - `scripts/service-contract-regression.mjs` — its locale guard treated every `commission*` key as retired, which is no longer true since the commission ledger came back.
- **2026-10-12 — W9 dropped; PR- series adopted.** The requester directed that the service-contract identity-field work be omitted entirely rather than re-planned, and that generated documents use a `PR-` series. `LoanReceiptService` now prints `PR-<year>-<id>`, with the feature test and the print regression script switched over; the `LN-` prefix appears nowhere in `app`, `resources`, `tests`, `scripts`, `database` or `lang`. `scripts/service-contract-regression.mjs` was also repaired: its locale guard still treated every `commission*` key as retired, which stopped being true when the contract-driven commission ledger was restored in the previous round.
- **2026-10-12 — Follow-up B done** (see §4c): the `completed` terminal case stage now triggers commission, and terminal stage moves were calibrated in the visa case workspace. Full PHPUnit green at **363 tests / 3445 assertions**; typecheck, i18n and icon checks pass; the five regression scripts pass.
- **2026-10-12 — W4b done: academy data nav relocated.** The Academy data link now sits under **Options → Contracts** (`navigation/horizontal/settings.ts`) beside Educational data, and no longer under the top-level Contracts group (`navigation/horizontal/contracts.ts`). The route name, path (`/contracts/academy-data`), permission (`view_country`) and the existing `common.navigation.academyData` key in en/fa-AF/ps-AF are unchanged, so deep links keep working and no locale edits were needed. Comments in `routes.ts` and `pages/educational-master-data.vue` now name the new location.

---

## 4b. Follow-up — Applications page: breadcrumbs removed, stages became the table tabs

**Requested after the round closed.** The page kept two navigations that duplicated each
other: a breadcrumb trail in the header and a dedicated stage strip of buttons. The stage
strip also competed with the four list tabs (Active work / Completed / Cancelled / All),
which were themselves just pre-grouped sets of those same stages.

**What changed**

| Surface | Change |
|---|---|
| `components/erp/ErpPageHeader.vue` | New `breadcrumbs` prop (default `true`). The Applications page passes `:breadcrumbs="false"`. |
| `pages/applicants.vue` | The `#navigation` slot and its stage buttons are gone, so the header renders no sub-navigation on this page (`links` is empty for the `applicants` section). |
| `pages/applicants.vue` | The stage strip moved into the data table's own `<VTabs>`: **All applications**, then every open pipeline stage in workflow order, a divider, then the terminal stages (Completed / Cancelled / Rejected / Withdrawn) each marked with its own icon and colour and a tooltip explaining that a case there is closed. |
| `pages/applicants.vue` | The tab value is the `stage` key itself, so the retired `board` parameter is no longer sent; `?stage=<key>` deep links from the dashboard, the pipeline overflow link and the visa report still select the matching tab. The now-redundant stage select was dropped from the filter menu (`stage` still counts as an active filter and is cleared by *Reset*). |
| `lang/{en,fa-AF,ps-AF}.json` | New `educational.terminalStageTab` tooltip string. |

Because the boards were derived from stages (`operation` = still open, `final` = the
`completed` stage, `cancelled` = the three exit stages, all of which set `status = closed`
through `ApplicantController::changeStage`), the merge loses no filtering power: every
board bucket is now reachable as its own stage tab, and the landing view is **All
applications** (the `status` filter covers the old "active only" view).

**Verification:** new `scripts/applications-tabs-regression.mjs` mounts the real page in
Chrome (real Vuetify, real `ErpPageHeader`) with a mocked applicant API and asserts: no
breadcrumbs, no separate stage strip, the stages as one-row tabs in workflow order with
the terminal stages after the separator, a stage tab requesting `stage=<key>` and never
`board`, *All applications* clearing the filter, and a single-row scrollable strip on
mobile — in LTR and RTL and in light/dark. `npm run typecheck`, `i18n:check`, `i18n:test`
and `build:icons` all pass.

---

## 4c. Follow-up — case `completed` stage is the commission trigger

**Requested after the round closed.** Terminal stages in *move case to stage* were not
calibrated, and commission was being booked on the **contract** status rather than on the
case actually finishing. The trigger is now the case reaching the `completed` terminal
stage.

**Backend**

| Surface | Change |
|---|---|
| `app/Models/ApplicantStage.php` | New `COMPLETED_KEY` (`'completed'`), `EXIT_KEYS` (`cancelled`/`rejected`/`withdrawn`) and `isCompletedStage()`, so "finished" and "exited" are named in one place instead of magic strings. |
| `app/Models/Applicant.php` | `isCaseCompleted()` — true only when the current stage is the completed stage. |
| `app/Models/ApplicantStageHistory.php` | `booted()` `created` hook funnels **every** case move (API, imports, seeders) into `CommissionService::syncForApplicant()`, stamped with the move time. |
| `app/Services/CommissionService.php` | Rewritten around the case: `syncForApplicant()` / `voidForApplicant()` / `syncForContract()` / `voidForContract()`. Accrues only when the case is in `completed`; the contract must be non-cancelled and `active`/`completed`; returns quietly when no base currency exists. `earnedAt` falls back to the completion history row. |
| `app/Models/Contract.php` | `booted()` always re-syncs on create and on `status` / `is_cancelled` change — the case decides, and the sync is idempotent. |
| `app/Http/Controllers/CommissionController.php` | `sync()` reconciles **cases** (current stage = completed) and voids pending accruals whose case has left `completed`; it now returns `{cases, voided}`. `accruals()` eager-loads the case. |
| `app/Http/Controllers/ApplicantController.php` | The `final` board filter uses `ApplicantStage::COMPLETED_KEY`. |
| `app/Http/Resources/CommissionAccrualResource.php` | Adds `case_id`, `case_name`, `case_stage_key`, `case_stage_name`. |

**Frontend**

| Surface | Change |
|---|---|
| `resources/ts/pages/applicant-case.vue` | *Move case to stage* now shows the open transitions first, then a **Final outcomes** subheader with the exit/terminal stages flagged and subtitled "Closes the case". Exit stages open a confirm dialog that explains the case closes without commission; the completed stage opens one that notes commission is booked. A case already in a terminal stage renders no move control and shows a status alert (success + commission note when completed, info + reopen hint otherwise). |
| `resources/ts/pages/commissions.vue` | The reconcile action is "Reconcile completed cases"; a **Case** column links each accrual to its case and shows the case's stage chip. |
| `resources/ts/api/{applicants,commission}.ts` | `current_stage` carries `is_terminal`/`allowed_next_keys`; `COMPLETED_APPLICANT_STAGE` is exported; `sync()` returns `{cases, voided}` and accruals carry the `case_*` fields. |
| `lang/{en,fa-AF,ps-AF}.json` | New `educational.caseUi` strings (final outcomes, confirm notes, closed banners) and reworded `common.crmUi.commissions` copy. |

**Other stage consumers audited:** the applicant pipeline reads `allowed_next_keys` (unchanged),
the dashboard/report stage breakdowns key off the stage rows rather than the old boards, and
`CaseWinService` still snapshots on `visa_approved` only — so no other surface needed a change.

**Verification:** `tests/Feature/CommissionLifecycleTest.php` was rewritten (10 tests) around a
real `change-stage` walk — completion accrues, a completed contract alone does not, an exit
stage does not, a base-currency-less completion still closes the case, cancelled contracts
void, and `sync` voids an accrual whose case left `completed`. New
`scripts/case-stage-move-regression.mjs` mounts the real visa case page in Chrome (LTR/RTL,
light/dark) and asserts the calibrated option order and subheader, the exit confirm dialog
sends nothing until confirmed, a completed case shows no move control, and mobile does not
overflow.

---

## 5. Known follow-ups (not part of this round)

- The CRM deep link (`/all-customers?contact=<id>`) auto-opens the contact menu only when that applicant's row is on the page the CRM table has already loaded; the list is server-paginated, so a deep link to an applicant on another page only highlights nothing. Fixing it means passing the id into the list query (or fetching the row) when `contact` is present.
- Payment-request print layouts are verified at the payload and component level; the **printed paper layout** (A5 slip vs. voucher) has not been checked in a browser/PDF this round.
- Commission accrues on the contracted fee (`fee − discount`) rather than on realised income, and is booked on the case completing — a contract the agency later cancels voids the pending accrual, but a case re-opened after completion leaves the already-earned accrual in place until it is reconciled.
