# Educational visa firm coherence plan

Date: 2026-10-09

## Review and boundaries
Fresh full structural graph extraction via graphify (code-only, excluding denied paths and generated/vendor assets); post-implementation graph refresh: 12,149 nodes, 27,299 edges and 439 communities; follow graph queries with direct reads of the relevant implementations. Existing working-tree changes are preserved. Applicants remain the shared CRM/case record. Built-in contracts remain route-driven private, scholarship, test_offer and academy documents. Issued content, template-version snapshots and receipt numbers must remain immutable. Dedicated reporting pages are outside the print redesign.

## Implementation sequence
1. Fix shared list printing: isolate the current report from the application shell, avoid fixed-position multi-page tables, wait for fonts/images, print report scope and company branding. Audit all non-report print entry points; maintain A4 contracts and A5 receipts/payslips. Add company logo and company-name footer to employee documents.
2. Retire the legacy Home dashboard through static routing. Home opens agency overview; management-only permissions protect overview and visa reporting. Hide the Applicants navigation group from regular employees while keeping authorized CRM access.
3. Replace applicant/pipeline header navigation with stage filter buttons; retain scope-aware filters and stage definitions. Remove the CRM add-applicant action, add contact shortcuts and relationship notes/history, and expose father contact fields.
4. Retire marketer APIs, selectors, attribution, permissions and schema with a forward migration; preserve employee identities and historical issued snapshots. Introduce optional visitor counselor assignment and applicant admission assignment using active employee position/role validation and typed API options.
5. Normalize non-destructive accent actions to the global primary theme. Move contract filters including dates into the existing filter menu; add filtered in-page list reports for all service contracts; serialize cancellation flags as Laravel booleans.
6. Show contract versions rather than template types in contract forms/tables. Service route determines template; exact active country/language version is selected automatically and shown before issue. Server retains authoritative version resolution.
7. Move version management above template content. Widen both editing dialogs; keep toolbars on one horizontal line with overflow on narrow screens, isolate editor scrolling, and style lists/quotes using logical spacing for RTL.
8. Replace service-specific template creation shortcuts with a unified filter menu. Templates filter by type, service, position, country/language and date; clauses filter by type, service, employee position, country/language, status and dates. Refresh placeholders from the current applicant/service fields while retaining resolution of existing issued tokens.

## Verification
Use npm run typecheck, icon generation and targeted Laravel SQLite feature tests. Add meaningful regression coverage for staff assignments, marketer retirement, version resolution, cancelled-contract listing and restricted overview access. Verify shared printing with browser media emulation/PDF where available, including consecutive print modes, long tables, RTL and company artwork. Record actual results and any environment limitations here.

## Delivery status
Implementation completed in the working tree. The existing working-tree changes were preserved. No migrations were run against the live database.

### Delivered
- Shared branded list printing isolates the selected report from application navigation and headers. Contract, payslip and attendance entry points use the shared print lifecycle. Contracts use A4, payslips/receipts use A5, and lists/attendance use A4 landscape. Conditional page breaks prevent forced trailing blank sheets; contract content can flow without fixed-height clipping.
- Home routes to agency overview. New overview permissions and existing visa-report permissions protect both navigation and backend endpoints; regular CRM staff retain their authorized CRM/case access.
- Applicant stage buttons filter the same case list; old pipeline URLs redirect to that list. CRM applicants have contact shortcuts, relationship notes/history and optional admission assignment, without a create action. Visitors can optionally select active counselors. Family contacts are captured during service contract creation.
- Marketer routes, UI, attribution, permissions and live schema are retired through a forward migration. Employee records and issued contract snapshots are preserved.
- Contract search/status/party/date filters are in the filter menu, cancellation booleans are serialized correctly, and each service contract list has a branded report. Route-specific service templates and exact current active country/language versions are shown automatically; issuance remains validated by the server.
- Template version management appears before content; editing dialogs are wider, toolbars remain on one scrollable row, and editor stacking/list/quote spacing are corrected. Template and clause filters share current scopes. Current applicant placeholders are translated for English, Dari and Pashto; legacy applicant tokens continue to resolve existing drafts.

### Validation results
- Laravel SQLite tests: **36 passed, 344 assertions** across AgencyWorkflowCoherenceTest, ApplicantCaseViewTest, BuiltInContractGenerationTest, ContractTemplateUpdateTest, ContractTemplateVersionTest and GenericAgreementTest.
- `npm run typecheck`: passed.
- `npm run build:icons`: passed, with no missing-icon warnings.
- Focused PHP formatting: passed.
- Receipt regression: passed 28 copy/paper grouping configurations, 21 language/paper rendering configurations, print cleanup/error handling and consecutive list-to-A5 print-mode isolation.
- Actual list/contract/cover/payslip components and styles compile with expected branding, family/employee data and final content markers. Four actual service forms compile with current options fixtures.
- **Visual browser/PDF checks remain unverified**: Chromium cannot start because this execution environment denies required socket operations, including after approved retries. Runnable checks are in `scripts/agency-document-regression.mjs` and `scripts/educational-ui-regression.mjs`.
- **Production build remains unverified**: the installed dependencies lack `@rollup/rollup-linux-x64-gnu@4.63.1`. Registry DNS fails both inside the sandbox and on the approved retry, preventing recovery. The build stops before compilation.

### Database rollout
Apply the two new `2026_10_09` migrations in the target environment. The first adds counselor/admission assignments, retires marketer schema/permissions and updates management grants; the second adds service/position clause scopes. The marketer retirement migration has no automatic rollback. Existing issued content snapshots and receipt numbers are retained. Accounts must refresh their authorization state after rollout.


### MySQL migration recovery correction
The initial retirement migration assumed conventionally named foreign keys on every marketer column. An existing MySQL database had an unconstrained `applicant_contract.marketer_id` column, causing error 1091 after earlier DDL had committed. The migration now inspects actual foreign keys and drops only existing constraints by their actual name (or columns for SQLite). Assignment column creation and constraint creation are guarded separately so rerunning a partially applied migration can safely resume. Regression coverage recreates an indexed marketer column without a foreign key, a custom-named legacy constraint, an already-created assignment column, and a missing assignment constraint.
Validation of the recovery correction: 8 workflow feature tests passed (52 assertions); 2 migration recovery tests passed (12 assertions), using SQLite. PHP syntax, focused Pint formatting and whitespace checks passed. The target MySQL database was not modified by these checks; rerun `php artisan migrate` there to resume the failed migration.
