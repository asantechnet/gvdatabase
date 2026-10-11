# Educational Comments — Consolidated Master Plan & Progress

**Created:** 2026-10-11
**Owner:** Engineering
**Status:** In progress — see [§4 Progress tracker](#4-progress-tracker) and [§5 Progress log](#5-progress-log)
**Source request:** [`docs/Educational New Comments++.md`](./Educational%20New%20Comments%2B%2B.md) (rounds 1–4 + the "Visa Case Page, loans, contract template" scope)

This document is the **single master index** for every comment round. It records which
requests are already planned/implemented (with a link to their detailed plan) and fully
plans the items that no earlier document covered.

Update the tracker **and** the log after every sub-task.

---

## 0. Confirmed decisions (asked before planning)

| # | Question | Decision |
|---|----------|----------|
| 1 | Session scope | **Plan + implement everything**, including a validation pass over the already-built rounds. |
| 2 | Personal task isolation | **Per-user private boards**, auto-created, visible only to the owner (and super-admin). |
| 3 | "Email delivery optional" | **Toggle + optional fields.** When off, SMTP/company-email values are optional and outgoing mail falls back to the env mailer. |
| 4 | Signed-artwork deletion | **All uploads** (logo, signature, contract letterhead/cover, receipt + payslip letterheads) get a confirm-protected delete that removes the stored file and clears the setting. |
| 5 | Income/Expense range filter | Filter on the **record's `date`** column. |
| 6 | Contracts layout unification | **Service-contract pages match `contract_list.vue`** — one in-card toolbar holding filter + print + Add. |
| 7 | Payment-override warnings | **Confirmation step + persistent in-dialog notice.** |

---

## 1. Prior rounds — status (no work outstanding)

These were delivered in earlier sessions and are *not* re-implemented here; they are only
re-validated in §3.

| Round | Item | Detail plan | State |
|-------|------|-------------|-------|
| R-print | Data-table print margins; blank icon-only buttons; KPI-row removal; Income/Expense header coherence; Test-Offer test-scoped versions; Academy hidden template + slip; Responsible-employee → Counselor; service-board seeding | [`contracts-tasks-print-revamp-plan.md`](./contracts-tasks-print-revamp-plan.md) | Done (D1/D2 open there, D2 covered by D3) |
| R-reports | Visa Reports theme-independent print; View-popup footer text removal; Employment/Other contract extension; cancelled-contracts `client → counterparty`; service-contract cancel action; cancelled label + print-disabled matrix; localized generated-at | [`visa-reports-contracts-cancel-print-plan.md`](./visa-reports-contracts-cancel-print-plan.md) | Done (physical PDF eyeball + dead global middleware open there) |
| R-extend | Extend only for Academy; Academy extend autofill; employment name-only versions; HR employment version auto-select + inferred position; contract-print scroll-lock (blank page + blue ribbon) | [`contracts-extend-versioning-print-plan.md`](./contracts-extend-versioning-print-plan.md) | Done |
| R-visa | Remove "Services Sold" from the visa case; loans print like receipts; party details onto page 1; cover zigzag removal | [`visa-case-loans-contract-print-plan.md`](./visa-case-loans-contract-print-plan.md) | Done |

Already-satisfied items from round 1, recorded for completeness:

- **Payment-override RBAC** is in place: `override_contract_payments` permission, route
  middleware `permission:view_incomes,permission:override_contract_payments`, the
  `apiAuthorization.ts` mirror, and `ApplicantContractPaymentTest`
  `test_payment_overrides_require_a_separate_finance_permission`. Only the *warning* part
  remained → N8.
- **Loans printing** (round 1's "Pending Transactions / Loans") is the same work as R-visa
  R2 → already done.

---

## 2. New workstreams

### N1 — Personal task isolation (per-user private boards)

**Problem.** Boards are entirely shared. `boards` has no owner, `BoardController@index`
lists every board the user is a pivot member of, and `TaskAccess::canViewBoard` grants
access from membership alone. The seeded `Personal` board is a single shared column, so
one employee's "personal" tasks are visible to anyone scoped to that board.

**Design.** A board with a non-null `boards.user_id` is a **private personal board**.

**Backend.**
1. Migration `2026_10_11_xxxxxx_add_user_id_to_boards_table.php`:
   `$table->foreignId('user_id')->nullable()->after('order')->constrained('users')->nullOnDelete();`
   plus a `boards_user_id_index`. Null = shared operational board.
2. `app/Models/Board.php` — add `user_id` to `$fillable` and an `owner()` `belongsTo(User::class, 'user_id')` relation; add `isPersonal()` helper.
3. `app/Support/ServiceBoard.php` — add
   `personalFor(User $user): Board` (idempotent `firstOrNew(['user_id' => $user->id, 'title' => self::PERSONAL])`,
   assigning the next `order`), and keep `titleFor()` for service boards. Remove the shared
   `Personal` board from the seeded order (the personal board is per user now).
4. `app/Support/TaskAccess.php::canViewBoard` — a personal board (`user_id !== null`) is
   visible only to its owner and to a super-admin; membership never widens it. The same
   guard covers view/update/delete through `BoardPolicy` (which delegates here).
5. `app/Http/Controllers/BoardController.php@index` — ensure the requesting user's personal
   board exists (idempotent), then return: shared boards filtered by the existing
   membership/`view_all_boards` rule, **plus** their own personal board, **plus** (super-admin
   only) all personal boards. Personal boards never leak to other users, even with
   `view_all_boards`.
6. `BoardResource` — expose `user_id` and a boolean `is_personal` / `is_owned`.
7. Guard service/board automation: `ContractTaskService`/`ApplicantTaskService` keep using
   service titles via `ServiceBoard::titleFor()`, so they never touch personal boards.
8. `database/seeders/BoardSeeder.php` — seed the four shared service boards; drop the shared
   `Personal` board (created per user on demand). `KanbanSeeder`/`TaskSeeder` continue to
   place manual/operational work through the personal board resolver rather than a fixed id.

**Frontend.**
9. `resources/ts/api/kanban.ts` — add `user_id: string | null` and `is_personal: boolean` to `Board`.
10. `resources/ts/stores/useTaskStore.ts` — default new-task board = the user's personal board
    (`boards.find(b => b.is_personal)`) instead of a title lookup; block drag/membership UI on
    personal boards where it makes no sense.
11. `resources/ts/components/task-manager/**` — label the personal column and hide
    board-management affordances (settings/member management) on it.

**Acceptance criteria.**
- After `db:seed`, each user opening the Tasks page gets exactly one personal board that no
  other non-super-admin user can list, view, or fetch tasks from.
- API-level isolation: `GET /boards` and `GET /tasks` never expose another user's personal
  board/tasks; a direct `GET`/`PUT`/`DELETE /boards/{personal}` by another user is 403/404.
- Service-delivery tasks still land on their service boards; manual tasks default to the
  caller's personal board.
- Backend test: personal-board isolation (list, policy, and task visibility).

### N2 — Unify the service-contract page layout with `contract_list.vue`

**Problem.** `service-contracts.vue` puts **Add** in `ErpPageHeader#actions` and the
**filter + print** in a detached `div.d-flex` row above the table. `contract_list.vue` puts
all three inside the data-table card's `VCardText > VRow.table-toolbar`.

**Change.** Restructure the four service pages so the toolbar lives inside the table card:
`VCard > VCardText > VRow.table-toolbar` (filter menu, print, And Add), then `VDataTable`.
Remove the standalone action row and the header Add button. Keep the page header for the
title only. Match `contract_list.vue`'s right-aligned `.table-toolbar-actions` layout and
`flex-shrink-0` on icon buttons.

**Acceptance criteria.** Service pages are visually identical in toolbar placement to
`contract_list.vue`; Add/filter/print sit inside the card; light/dark + LTR/RTL + mobile.

### N3 — CRM → Applicants toolbar (`all_customers.vue`)

**Problem.** Print sits in `VCardTitle`; the search field is unconstrained and stretches.

**Change.** Move the print button into the toolbar row beside `DataTableFilterMenu`, and give
the search field a fixed width (`class="data-table-search"` + `max-width` / `flex: 0 0 …`) so
it no longer stretches. Keep the title row clean.

**Acceptance criteria.** Print and filter are adjacent; search is a fixed narrow width that
holds on mobile (full-width only below the small breakpoint).

### N4 — Contract template: company signature must not drop

**Problem.** In `contract_page_template.vue::signature-section`, the company box renders an
`<img class="company-signature">` above `.sig-line`, so when the signature loads from
settings the company name/date shifts down relative to the contractee column.

**Change.** Reserve an equal-height signature slot in **both** boxes (the contractee box gets
an empty slot), so the `.sig-line`, name and date stay aligned regardless of whether the
image is present. Keep the image object-position/size rules.

**Acceptance criteria.** With and without a loaded signature, both columns' lines/names/dates
align; print output unchanged otherwise.

### N5 — Settings: email delivery optional

**Problem.** `UpsertSettingRequest` requires `company_email` on create
(`$required = 'required'`), and the settings email page treats SMTP fields as mandatory.

**Backend.**
1. Migration: add `settings.email_delivery_enabled` (boolean, default `false`).
2. `Setting` — fillable + boolean cast; `SettingResource` — expose the flag.
3. `UpsertSettingRequest` — `company_email` becomes `['sometimes','nullable','email:rfc','max:255']`;
   add `email_delivery_enabled` (`sometimes|boolean`); keep SMTP fields optional.
4. `app/Support/CompanyProfile.php` — `configureMailer()`/`mailFrom()` return null when the
   flag is off (env mailer used), so an unconfigured install still works.

**Frontend.**
5. `api/setting.ts` — add `email_delivery_enabled: boolean` to `Setting` + `SettingForm`.
6. `settings.vue` email page — add a switch; when off, hide/disable the SMTP fields, mark all
   as optional, and stop sending them. Show a short hint that mail uses the server default.

**Acceptance criteria.** Company settings save with no email values at all; the toggle
persists; with it off, outgoing mail uses the env mailer; with it on, the current SMTP
behaviour is unchanged. i18n keys in all three locales.

### N6 — Settings: delete uploaded artwork

**Problem.** `SettingController::REMOVABLE_FILES` covers the four letterheads/covers but not
`website_logo` or `company_signature`, so those two can never be removed.

**Backend.**
1. `UpsertSettingRequest` — add `remove_website_logo` / `remove_company_signature`
   (`sometimes|boolean`) and `Rule::prohibitedIf` on the matching upload.
2. `SettingController` — add both fields to `REMOVABLE_FILES` and to the `Arr::except`
   lists; the existing loop already deletes the superseded file from disk.

**Frontend.**
3. `api/setting.ts` — add the two flags to `SettingForm`.
4. `settings.vue` — add confirm-protected delete actions for logo + signature and for each
   letterhead/cover (which already have flags), clearing the preview. Keep every deletion
   behind a confirmation dialog.

**Acceptance criteria.** Each uploaded asset can be deleted; the stored file is removed and
the setting cleared; cancel leaves everything intact.

### N7 — Income/Expense date-range filters

**Problem.** `IncomeController@index` and `ExpenseController@index` accept no date range.

**Backend.**
1. `IncomeController@index` / `ExpenseController@index` — validate
   `date_from` / `date_to` (`date_format:Y-m-d`, `after_or_equal`) and filter with
   `whereDate('date', …)`.

**Frontend.**
2. `income.vue` / `expenses.vue` — add `ErpDateRange` inside `DataTableFilterMenu`, extend the
   request params, the `active` predicate, and the reset handler.

**Acceptance criteria.** The range filters the list to the record `date`; reset clears it;
the active-filter indicator reflects it.

### N8 — Payment-override warnings

**Problem.** `PaymentOverridesDialog.vue` saves overrides with no warning and no confirmation.

**Change.** Add a persistent `VAlert` explaining that an override changes the collectible
balance (not the receipt ledger) and is audited, plus a confirmation dialog before saving;
keep the existing permission gating. i18n keys in all three locales.

**Acceptance criteria.** Opening the dialog shows the notice; saving requires confirming;
cancel does not persist; tests/`i18n:check` pass.

### N9 — Cancelled contracts toolbar placement

**Problem.** `cancelled_contracts.vue` renders the filter menu + print inside
`ErpPageHeader#actions`, which the requester sees as "the middle of the page".

**Change.** Move the filter menu and print button into the data-table card's top-right
toolbar corner (consistent with `contract_list.vue` and N2). Keep the page header for the
title only.

**Acceptance criteria.** Filter + print sit in the card's top-right corner on desktop and
stack cleanly on mobile.

---

## 3. Validation strategy

Run after the workstreams and before declaring done:

1. `npm run typecheck` (never bare `vue-tsc`/`tsc`).
2. `npm run i18n:check` + `npm run i18n:test`.
3. `npm run build:icons` — treat any "used but unavailable" warning as a bug.
4. Node regressions: `receipt-print-regression.mjs`, `loan-receipt-print-regression.mjs`,
   `service-contract-regression.mjs`, `agency-document-regression.mjs`,
   `contract-print-regression.mjs`, `contract-workflow-regression.mjs`,
   `report-print-locale-regression.mjs`.
5. Backend: `DB_CONNECTION=sqlite DB_DATABASE=":memory:" php artisan test --filter=…` for the
   touched suites (boards/tasks, settings, income/expense, payment overrides), plus
   `vendor/bin/pint` and `php -l` on changed PHP.
6. Manual matrix per UI change: light + dark, LTR + RTL, mobile/tablet/desktop.

Environment limitations carried over from earlier rounds: no local MySQL (tests run on
SQLite in-memory) and the Linux-native rollup binary may be missing for `npm run build`.

---

## 4. Progress tracker

Legend: `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked

| ID | Sub-task | Status | Notes |
|----|----------|--------|-------|
| N1 | Per-user private personal boards (isolation end to end) | [x] | `boards.user_id` migration; `Board::isPersonal()`/`owner()`; `TaskAccess`/`BoardPolicy` owner-only access; `BoardController@index` ensures the caller's board and returns own personal + shared (super-admin sees all); `ServiceBoard::personalFor()`; seeders (Board/Kanban/Task/TaskUser) reworked; `BoardResource` + `kanban.ts` expose `is_personal`/`is_owned` |
| N2 | Service-contract pages match `contract_list.vue` in-card toolbar | [x] | `service-contracts.vue` toolbar (filter + print + Add) moved inside the table card; detached row and header action removed |
| N3 | CRM Applicants: print beside filter, fixed narrow search | [x] | `all_customers.vue` print moved next to the filter menu; search uses `data-table-search` (fixed 280px, full-width on mobile) |
| N4 | Contract-template company signature stays aligned | [x] | Fixed-height `.signature-slot` in both `signature-box`es of `contract_page_template.vue` |
| N5 | Email delivery optional (flag + optional fields + env fallback) | [x] | `settings.email_delivery_enabled` migration; `company_email` optional; `CompanyProfile` gates SMTP/from; settings page switch hides the SMTP fields; 4 i18n keys × 3 locales |
| N6 | Delete logo/signature/letterheads with confirmation | [x] | `remove_website_logo`/`remove_company_signature` + `REMOVABLE_FILES`; settings page delete buttons with confirm; letterhead removals now confirm too |
| N7 | Income/Expense date-range filters | [x] | Backend `date_from`/`date_to` on the record `date`; `ErpDateRange` added to both filter menus |
| N8 | Payment-override notice + confirmation | [x] | `PaymentOverridesDialog` notice alert + confirm dialog; 4 i18n keys × 3 locales |
| N9 | Cancelled-contracts toolbar in the card's top-right corner | [x] | `cancelled_contracts.vue` filter + print moved into the card's `VCardText` toolbar |
| V | Validation pass (typecheck, i18n, icons, node regressions, PHPUnit) | [x] | `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test` (4240 keys × 3 locales), `npm run build:icons` (no unavailable icons), 10 Node regressions, `migrate:fresh --seed`, Pint, `php -l`, and 78 targeted PHPUnit tests (679 assertions) all pass. `npm run build` is still blocked by the missing Linux rollup binary. Two unrelated pre-existing failures remain (see log) |

---

## 5. Progress log

- **2026-10-11** — Consolidated review of rounds 1–4 plus the new visa-case/loans/contract
  scope. Confirmed rounds 2–4 and the visa-case scope are already delivered (links in §1) and
  that payment-override RBAC + loan printing already exist. Identified the un-planned work as
  N1–N9 and confirmed the seven decisions in §0. Plan created; implementation starting.
- **2026-10-11** — N1 done. `boards.user_id` (nullable FK) marks a private personal board.
  `Board::isPersonal()`/`owner()` and a reusable `scopeVisibleTo()`; `TaskAccess::canViewBoard`
  and `BoardPolicy` grant a personal board to its owner only (+ super-admin), never through
  membership or `view_all_boards`. `BoardController@index` and `KanbanController@index` ensure
  the caller's board on read and return own-personal + shared; `BoardResource`/`kanban.ts`
  expose `user_id`/`is_personal`/`is_owned`. `ServiceBoard::personalFor()` plus reworked
  Board/Kanban/Task/TaskUser seeders (Personal board removed from `ORDER`; `titleFor(null)`
  now falls back to Applicant Cases). New `PersonalBoardIsolationTest` (2 tests).
- **2026-10-11** — N2–N4, N9 done. Service-contract pages now use one in-card toolbar
  (filter + print + Add) like `contract_list.vue`; `all_customers.vue` print moved beside the
  filter with a fixed 280px search; the cancelled-contracts filter/print moved into the
  card's top-right corner; `contract_page_template.vue` gained a fixed-height signature slot
  in both boxes so the company signature can no longer push its column down.
- **2026-10-11** — N5/N6 done (settings). Added `settings.email_delivery_enabled` and made
  `company_email` optional; `CompanyProfile` ignores the company SMTP/from identity when the
  flag is off (env fallback). Added `remove_website_logo`/`remove_company_signature` to the
  request + `SettingController::REMOVABLE_FILES`; the settings page now offers confirm-protected
  delete actions for the logo, signature and the letterheads. New
  `SettingsEmailAndFileRemovalTest` (3 tests). i18n: 8 new keys across en/fa-AF/ps-AF for these
  plus the payment-override copy.
- **2026-10-11** — N7/N8 done. `IncomeController`/`ExpenseController` index accept
  `date_from`/`date_to` on the record `date`; both pages gained `ErpDateRange` in their filter
  menu. `PaymentOverridesDialog` shows a persistent warning and requires a confirmation before
  saving.
- **2026-10-11** — Validation pass. `npm run typecheck` ✓; `npm run i18n:check` ✓ (no missing
  keys) and `npm run i18n:test` ✓ (4240 keys, en/fa-AF/ps-AF); `npm run build:icons` ✓ (287
  tabler references, no unavailable-icon warnings); Node regressions ✓ (`receipt-print`,
  `loan-receipt-print`, `service-contract`, `agency-document`, `contract-workflow`,
  `report-print-locale`, `contract-print`, `academy-slip-print`, `income-payment`,
  `finance-types`, `educational-ui`); `php artisan migrate:fresh --seed` ✓ (new migrations +
  reworked seeders run clean); `vendor/bin/pint` ✓; `php -l` ✓; PHPUnit ✓ **78 tests /
  679 assertions** across the board/task/settings/income/expense/dashboard suites including the
  two new files.
- **2026-10-11** — Notes. `npm run build` remains blocked by this environment's missing
  Linux-native `@rollup/rollup-linux-x64-gnu` (dependency install, not code). Two failures in
  the wider run are **pre-existing and unrelated**: `CurrencyRateServiceTest > income and
  expense snapshots cannot override the base rate` (the test seeds no `finance_types`; the
  `exists` rule it hits is unchanged at HEAD) and `EducationalWorkflowIntegrationTest >
  staff dashboard …` (documented as an open baseline failure in
  `BASELINE_TEST_FIX_HANDOFF.md`; untouched file). `RoleBasedAuthorizationTest` was updated to
  assert the new per-user personal board while keeping its shared-board security assertions.
  Physical browser/PDF verification of the print changes still needs a manual check.
