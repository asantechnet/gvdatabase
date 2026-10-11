# Visa Firm Simplification: Completion Report

Review date: 2026-10-08.

## Outcome

Phases A-G are implemented. The final review fixed the remaining shared currency-rate gap and both reported setup failures: the employee-status migration referenced the removed `employment_type` column, and the portal demo seeder wrote the retired `application_date` field. Complete demo-chain coverage also exposed destructive, MySQL-only currency seeding; defaults now preserve existing records. The plan's stale statement that other currency-rate portals were pending has been corrected.

Implementation completion is not a deployment or visual-acceptance certification. The manual printing checkbox remains open; production build/preview and browser acceptance remain unverified. No working-database migration was applied, no Playwright was used, and no dependencies or lockfiles were changed. Further installation/download attempts were stopped at the user's request.

## Phase Coverage

| Phase | Implemented scope | Remaining acceptance |
| --- | --- | --- |
| A | Educational master-data schema, CRUD, API contracts, RBAC and translated UI. | None identified in implementation review. |
| B | Shared applicant identity with scholarship, test-offer and academy details, validation and editing. | None identified in implementation review. |
| C | Built-in templates, atomic applicant/contract/line/task creation and independent extension economics. | MySQL deployment remains to be rerun by the owner after the migration fix. |
| D | Service-line incomes, distinct receipts, payment calculations/overrides, optional applicants, expenses and shared rate persistence. | Manual A5/two-up, RTL/LTR, artwork and SPA print interaction acceptance. |
| E | Three contract workspaces, visitor identity prefill/action and automatic applicant creation. | Browser acceptance across viewport, theme and direction combinations. |
| F | Removed leads, catalogue services/packages, invoices, counterparty companies and commission/outsourcing storage and surfaces. | Owner-controlled application of cleanup migrations. |
| G | Current permissions, audits, notifications, translations and automated regression coverage. | Production build/preview; unrelated broad-project translation findings are not part of this scope. |

## Acceptance Criteria

| Criterion | Implementation review |
| --- | --- |
| AC1 | Leads and conversion-to-lead surfaces removed; visitor-to-applicant remains. |
| AC2 | Service/package catalogue models, tables, APIs, UI and permissions removed. |
| AC3 | One built-in service vocabulary and one editable template binding per service. |
| AC4 | Service-specific applicant details are validated, persisted and exposed consistently. |
| AC5 | Invoice/payment-detail models, storage and user-facing surfaces removed. |
| AC6 | Each selected service line produces its own income and receipt number. Receipt rendering/lifecycle tests pass; physical/PDF layout acceptance is still open. |
| AC7 | Counterparty companies and their references removed; own-firm branding and signatures remain. |
| AC8 | Applicant-contract lines own fee, currency, rate, discount and payment state. |
| AC9 | Assignment creates an idempotent task linked to the exact service line. |
| AC10 | Standalone incomes/expenses remain valid; supplied applicants are validated. |
| AC11 | Contracts, incomes, expenses and currency settings use the shared supplied-rate writer. Base-only payroll is not an editable exchange-rate portal. Pending/recurring workflows consume saved rates. Coordinated base switching is retained. |
| AC12 | Visitor prefill copies identity without modifying the visitor. |
| AC13 | Visitor row action opens the built-in contract workflow. |
| AC14 | Creation atomically writes applicant, details, contract, line and task. |
| AC15 | Educational reference CRUD, authorization and in-use protection are implemented. |
| AC16 | Paid/unpaid/partially-paid states are shared across the payment workflow. |
| AC17 | Owned workflow translations, current RBAC/audit resources and notification localization are covered. Broader unrelated UI-string cleanup is not certified. |

## Changes From This Review

### Currency Rate Persistence

`app/Services/CurrencyRateService.php` now rejects non-finite rates and values that round to zero, locks the currency row inside a transaction, keeps the base currency at rate one and returns the persisted normalized value. Nested transactions preserve the caller's rollback boundary, including audit writes.

`app/Http/Controllers/CurrencyController.php` delegates supplied rates on creation, ordinary updates and pre-rebase edits to that service. The controller still owns coordinated rebasing and currency-rate history. Settings reject rates below the schema's eight-decimal precision. Issued contracts and existing income/expense snapshots are not mass-updated.

### MySQL Migration Failure

`database/migrations/2026_10_07_000001_add_status_to_employees_table.php` no longer positions `status` after the removed `employment_type` column. It adds the active default and status index without a dependency on retired schema.

`tests/Feature/EmployeeStatusMigrationTest.php` compiles the actual migration through Laravel's MySQL grammar and verifies the status/default/index SQL has no retired-column anchor. This does not connect to the working MySQL database. Other migration `after()` clauses were reviewed for removed anchors.

### Regression Tests

`CurrencyRateServiceTest` now covers unusable/non-finite rates, base-rate protection, caller rollback, income/expense base-currency snapshots and settings creation/update/rebasing. Exchange-rate fixtures in `ApplicantContractPaymentTest`, `BuiltInContractGenerationTest` and `ReportedIssueRegressionTest` use non-base currencies, preserving their original rate-change/rollback intent.

### Demo Seeder Compliance

`ApplicantPortalDemoSeeder` removes the retired `application_date` input rather than adding a compatibility column. It explicitly seeds scholarship and creates/restores its detail row while retaining the same portal user, applicant and detail IDs on repeated runs.

`CurrencySeeder` no longer soft-deletes currencies, resets IDs or runs raw MySQL foreign-key/reset statements. It adds only missing default currency codes, preserves the existing base, reviewed rates and archived records, and leaves foreign defaults inactive. This makes isolated SQLite demo-chain testing possible without sacrificing MySQL behavior or existing financial references.

`ApplicantPortalDemoSeederTest` covers complete demo seeding, portal repeatability/restoration and currency-default preservation. The normal feature-test setup seeds permissions/roles, not the complete demo dataset; that gap explains why the earlier green suite did not expose the demo error.

### Template Picker Follow-Up

The customer-type template editor already offered Scholarship, Test Offer and Academy. The follow-up corrected a narrower UI lock: an unbound saved template can now choose a service, while saved built-in bindings stay immutable. Unsaved choices no longer prematurely lock the contract type, and services already assigned to another template are disabled. No catalogue table or compatibility API was restored.

Backend tests cover assigning all three services to unbound templates. The non-browser frontend regression evaluates the actual editor's computed binding lock and service availability, in addition to compiling its Vue template.

## Verification

The final full backend run passed **296 tests with 2,590 assertions**. A subsequent template-picker change and its added test were verified by a focused template/version rerun: **16 tests with 127 assertions**. The full-suite number precedes that last follow-up; it is not presented as a second full-suite run. All automated backend execution uses isolated SQLite and temporary storage, not the working database.

Passed in this review:

- `npm run typecheck`; `tsconfig.json` retains `noEmit: true`.
- `npm run i18n:test`: all three locales, 4,201 keys.
- `npm run build:icons`: 299 resolved references, no unavailable-icon warnings.
- Contract regression: nineteen Vue templates, API wrappers, authorization, visitor prefill, locale coverage and retirement guards.
- Income/payment regression: 42 assertions covering calculations, batching and API permissions/validation.
- Receipt regression: 28 grouping configurations, 21 RTL/LTR configurations, namespaced CSS and lifecycle/error cleanup. This is not browser/PDF layout verification.
- Focused currency/payment rerun: 25 tests, 211 assertions.
- MySQL status-migration compilation: one test, six assertions.
- Contract-generation/migration rerun: 12 tests, 133 assertions.
- Demo-chain/repeatability/currency-preservation tests: three tests, 23 assertions.
- Scoped PHP formatting and whitespace checks.

`npm run build` remains blocked by the missing local `@rollup/rollup-linux-x64-gnu` optional dependency. No replacement production bundle or preview URL is claimed. The existing generated assets from other work were not reverted or deleted. Source/API retirement does not prove that the existing production bundle is current; deployment requires rebuilding assets on an environment with the native dependency already available.

## Outstanding Steps

1. On the owner's MySQL environment, run any still-pending migrations with `php artisan migrate`, then retry the failed final seeder with `php artisan db:seed --class=ApplicantPortalDemoSeeder`. The earlier seeders already ran before that failure; restarting the entire demo chain is unnecessary. Do not use `migrate:fresh` or reset the database to resolve these missing-column errors. The full MySQL migration chain has not been executed here.
2. Complete manual A4 contract and A5/two-up receipt acceptance, including all supported directions, configured letterhead/signature, pagination and printing after visiting other document routes.
3. Verify the three service workspaces in a browser on mobile/desktop, light/dark and RTL/LTR. Automated Vue/type/locale checks do not establish visual correctness.
4. When the owner has the missing native dependency available locally, run the production build and preview on that environment. Installation is intentionally not being performed here.

There is no remaining feature implementation checkbox identified in the plan. These outstanding items are deployment/environment and visual/physical acceptance work; they have not been marked complete.
