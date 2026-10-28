## Task: Service-contract edit action and strict counselor-review options

- **Task ID:** service-contract-edit-action-and-strict-counselor-review
- **Created:** 2026-10-28
- **Last updated:** 2026-10-28
- **Status:** PARTIALLY COMPLETED (main delivered; mirror pending)
- **Outcome:** Every built-in service-contract list (scholarship, test offer, private, academy) offers a working Edit action, and a counselor review is stored as exactly one of `good`, `bad` or `great` instead of free text. Main is the source, the multi-tenancy branch mirrors it per `docs/facts/branch-mirroring.md`.

### 1. Requirements and acceptance criteria

- [x] The four service-contract lists expose an Edit action that opens the contract form and saves through `PUT /contracts/{service}/{id}`. Verified; no change needed.
- [x] A counselor review accepts only `good`, `bad` or `great` at every write path; any other value is rejected.
- [x] The visitor form, the visitor review dialog and the applicant review dialog each offer exactly those three choices, and the visitor list/print show the translated value.
- [x] Existing reviews are normalized by a migration so no stored value falls outside the three options.
- [ ] Main and the multi-tenancy branch both carry the behavior, with the branch's own regenerated bundle. (Main done; mirror pending.)

### 2. Scope and non-goals

- In scope: service-contract list action (if something is genuinely missing), visitor/applicant review validation, API types, UI selects, translations, seed data, migration, tests, main commit and multi-tenancy port.
- Out of scope: redesigning the service-contract form (an unrelated in-progress working-tree change is present), contract numbering, templates/policies, portal, permissions beyond what the Edit action needs.

### 3. Project findings

- `resources/ts/pages/service-contracts.vue` already renders a per-row Edit button at `HEAD`:
  `<VBtn v-if="ability.can('read', 'edit_contracts')" ... @click="edit(item)">`, for every service (no service condition), for non-cancelled rows.
- `ContractController::updateService` and `ContractGenerationService::update` already support all four services, and `POST/PUT /contracts/{service}/{id}` are registered.
- `edit_contracts` exists in `PermissionSeeder` and is granted to `admin`, `manager` and `team_lead` by `RolePermissionSeeder`; a super admin gets every permission row. The local database has the permission and the grants.
- The current working tree contains unrelated uncommitted edits (service-contract form sections, contract-number format, `FullDetailsButton` labels, locales, a test). They will be preserved and must not be committed as part of this task.
- Counselor review is currently free text:
  - `visitors.consultant_review` and `applicants.consultant_review` are nullable `text` columns (`2026_10_27_000003_replace_consultant_review_flag_with_notes.php` replaced the old boolean `reviewed_by_consultant`).
  - `VisitorController` validates `string|max:10000` in `store`, `update` and `updateConsultantReview`.
  - `ApplicantController` validates `string|max:10000` in `save` and `updateConsultantReview`.
  - UI textareas: `components/crm/VisitorProfileFields.vue` (add/edit form), `pages/visitor.vue` review dialog, `pages/applicants.vue` review dialog.
  - `VisitorSeeder` writes `Initial counseling review completed.`, which is outside the requested option set.
  - `VisitorResource` / `ApplicantResource` return the raw string; `pages/visitor.vue` renders it directly in the table and print row.

### 4. Graph and impact review

- Fallback used: repository-wide search (`consultant_review`, `edit_contracts`, service-contract routes, request/controller/service chain) plus the four service route entries in `plugins/1.router/routes.ts`.
- Affected callers found: `VisitorApi.updateConsultantReview`/`create`/`update`, `ApplicantApi.updateConsultantReview`, visitor create/edit/review flows, applicants review dialog, print report, visitor-to-applicant conversion (copies the value), seeders and `VisitorProfileAndServiceContractFieldSetsTest`.
- No graph artifact (`graphify-out/`) is present; the search/reference method above is the documented fallback.

### 5. Clarifications, decisions, and assumptions

- User decision (2026-10-28): test whether the existing Edit action works; if it does, omit that part and implement only the strict review options.
- Verification: `BuiltInContractGenerationTest::test_each_service_contract_updates_its_live_case_and_terms_without_rewriting_the_issued_snapshot` passes for all four services (1 test, 36 assertions). `service-contracts.vue` renders the Edit action for every non-cancelled row and calls `ContractApi.updateService`. No code change made for this half.
- Decision for the review: store the canonical lowercase values `good`, `bad`, `great`; blank means no review; display through `common.statuses` translations.
- Assumption for legacy data: preserve exact matches to the three options (case-insensitive), map the old `Reviewed` sign-off to `good`, and clear other free text, because free prose cannot be represented in a three-option field.

### 6. Implementation plan

- [x] Edit action verified as working; no service-contract change needed.
- [x] Add a small canonical value holder for counselor reviews and use it in both controllers.
- [x] Migration: normalize `visitors.consultant_review` and `applicants.consultant_review` to the three values.
- [x] Replace the three review textareas with a three-option select and translated display in the visitor table/print.
- [x] Update API types, seeder data and feature tests (valid values accepted, arbitrary text rejected for visitors and applicants).
- [x] Add the missing translations in `en`, `fa-AF`, `ps-AF`.
- [ ] Commit on main, then port to the multi-tenancy branch, place the migration under `database/migrations/office`, run the branch's typecheck/tests and rebuild its bundle.

### 7. Validation plan and results

- [x] `npm run typecheck` — passes.
- [x] `npm run i18n:check` — only the pre-existing `contract_list.vue` key (`pages.contracts.allContracts.allContracts.failedToFetchContractsPleaseTryAgain`) is missing; the new `common.statuses.bad` / `great` keys are used and present in all three locales.
- [x] Focused PHP feature tests — `VisitorProfileAndServiceContractFieldSetsTest` 9 passed / 88 assertions; `ConsultantReviewMigrationTest` 1 passed / 4 assertions (legacy `Reviewed`→`good`, prose→null, `GREAT`→`great`, applicant note→`good`). The fourth-service edit test from the omitted half also passed (1 test / 36 assertions).
- [ ] `npm run build` (and branch build for the mirror).
- Results: main source validated; build and mirror pending.

### 8. Risks and mitigations

- Free-text reviews would be invalid under the new rule — migration normalizes them before the API starts rejecting values.
- Unrelated uncommitted work in the main tree could leak into the commit or its bundle — stage only this task's files/hunks and do not rebuild into the user's uncommitted changes without resolving that first.
- The mirror must keep the migration in the tenant office set and rebuild its own bundle — follow `docs/facts/branch-mirroring.md`.

### 9. Progress log

- 2026-10-28 — DISCOVERY: inspected the service-contract page/API/controller/service chain, permission seeding and local grants; confirmed the Edit button already exists for all four services. Inspected every `consultant_review` writer and reader, the old boolean-to-text migration, UI locations, seeder and tests. Found unrelated uncommitted edits in the main tree and left them untouched. Status set to WAITING FOR CLARIFICATION for the Edit-action question.
- 2026-10-28 — DECISION: the user asked to test the Edit action and omit it if working. `php artisan test tests/Feature/BuiltInContractGenerationTest.php --filter=test_each_service_contract_updates_its_live_case_and_terms_without_rewriting_the_issued_snapshot` passed (1 test, 36 assertions across all four services); the button and API call are present at HEAD. The Edit action is therefore out of scope.
- 2026-10-28 — PLAN: keep canonical lowercase values (`good`, `bad`, `great`), enforce them in both controllers, normalize legacy rows in a migration, and replace all three review textareas with a three-option select. Proceed to implementation.
- 2026-10-28 — PROGRESS: added `App\Support\ConsultantReview`, restricted all four validation sites (`VisitorController` store/update/review and `ApplicantController` save/review), added the normalization migration and updated the seeder. Replaced the form and two dialog textareas with three-option selects, added `common.statuses.bad`/`great` in all three locales, and translated the visitor list/print cell. Updated API types and the review feature tests.
- 2026-10-28 — NOTE: while this task was in progress the unrelated working-tree edits were committed on main as `e7a233b` (service-contract list/details), followed by `f1b8821` (shared contract detail label); they were not touched by this task. Two more main commits, `8db98bd` and `e7a233b`/`f1b8821`, are unmirrored on the tenancy branch and will be included in the port to keep the branches at parity.
- 2026-10-28 — VALIDATION: `npm run typecheck` passes; `npm run i18n:check` reports only the known pre-existing `contract_list.vue` key; `php artisan test tests/Feature/VisitorProfileAndServiceContractFieldSetsTest.php` 9 passed / 88 assertions; `php artisan test tests/Feature/ConsultantReviewMigrationTest.php` 1 passed / 4 assertions.

### 10. Completion summary

- Delivered (main): counselor reviews are now strictly `good`, `bad` or `great` at the API, form and dialog levels; legacy values are normalized by migration; the visitor list/print show translated labels.
- Not delivered yet: the multi-tenancy mirror and its rebuilt bundle.
- Remaining limitation: free-text reviews are cleared by the migration because they cannot be represented by the three-point choice; the old `Reviewed` sign-off and existing exact values are preserved as `good`/their canonical value.
