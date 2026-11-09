# Progress — Task-046

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-11-09`
- Current focus: `Delivered and verified on the merged academy branch`
- Blocker: `No code blocker. Manual viewport/RTL and native-copy review remain unrun.`
- Next action: `Review the merged UI at 390/768/1280 px in LTR/RTL, then close the task.`

## Acceptance criteria progress
- [x] Given a new test-offer or academy contract, when it is created successfully, then the party, contract and service line exist, `applicant.current_stage_id` and `line.current_stage_id` are null, no `applicant_stage_histories` row is written, and no stage lookup is required.
- [x] Given an existing test-offer or academy applicant or line with a stage, when the retirement migration runs twice, then those stage references are null, the stage rows are inactive rather than deleted, stage histories/contracts/lines/incomes/commission rows are preserved, and the second run makes no further change.
- [x] Given a stage create, update or delete request for test-offer or academy, when it is submitted, then it is refused with `business.applicant_stage_service_not_supported` or the existing in-use/lock refusal, no stage row is written or removed, and the stage list for those services is empty.
- [x] Given the stage editor, when it renders, then only scholarship and private are selectable, the test-scope controls/column are absent, the locked chip/label is absent for terminal rows, and every locked row still has no edit/delete control.
- [x] Given the application pipeline, when it is queried or rendered, then no test-offer or academy line appears as a stage card, while scholarship/private cards and moves behave exactly as before; the contract registers still list the case-free contracts.
- [x] Given a scholarship or private contract created or edited with two responsible employees, when it succeeds, then both pivot rows exist, the response returns both ids and names, and clearing the array removes every pivot row without changing the counselor field.
- [x] Given a caller without `assign_responsible_employees`, when they submit or change `responsible_employee_ids`, then the request is 403, no pivot/contract/notification write occurs, and the options endpoint returns no responsible-employee list; a caller with the permission can choose multiple eligible employees.
- [x] Given existing applicants with a non-null legacy admission employee, when the pivot migration runs, then each employee is backfilled exactly once, a second run creates no duplicate, the legacy column is unchanged, and the new API returns only the plural fields.
- [x] Given a contract with two responsible employees and a counselor, when the delivery task and contract notice are created, then each responsible employee's user is assigned/notified once, the counselor remains assigned, duplicates are collapsed, and no removed employee receives a notice.
- [x] Given a completed scholarship/private case with multiple responsible employees, when commission sync runs, then accruals are still keyed and calculated only from the existing seller fields and their amounts are unchanged; responsible employees never appear as commission recipients.
- [x] Given a test-offer or academy case with existing commission accruals, when commission sync runs after stage retirement, then no new accrual is created, no existing accrual is voided or mutated, and settled money remains untouched.
- [x] Given the contract document placeholders, when `applicant.responsible_employees` is resolved, then it renders the joined names of every assigned responsible employee, the legacy `applicant.admission_employee` alias resolves the same names, and the registry label is plural.
- [x] Given the six locale/catalog files delivered by Task-043, when the changed pages and refusal path render in English, Dari and Pashto, then `serviceContracts.responsibleEmployees`, `serviceContracts.responsibleEmployeesOptional`, `serviceContracts.responsibleEmployeesHint`, `serviceContracts.placeholders.responsibleEmployees`, `agencyStaff.responsibleEmployees`, `educational.responsibleEmployees`, `common.fields.responsibleEmployees` and `api.errors.business.applicant_stage_service_not_supported` exist with non-empty matching values and no raw key.
- [ ] Given the focused suites, when `php artisan test --filter=ContractCaseBoundaryTest`, `--filter=ResponsibleEmployeesTest`, `--filter=CommissionResponsibilityRegressionTest`, `--filter=ApplicantStageContextTest`, `--filter=CommissionLifecycleTest` and the updated contract/applicant regressions run, then they pass on an isolated database; `npm run typecheck` and `npm run i18n:check` exit 0. — Evidence: suites and typecheck pass; `npm run i18n:check` still exits 1 on pre-existing unrelated keys.

## Work completed with evidence
- Case-free test-offer/academy generation, stage retirement, plural responsible-employee pivot/permission surfaces, notification and task assignment, commission preservation and document placeholders were delivered jointly with Task-037/Task-038 and verified on the merged academy branch.
- The locked stage chip was removed from `applicant-stages.vue`; locked rows still expose no edit/delete control and the server keeps the canonical terminal lock.
- Focused `ContractCaseBoundaryTest`, `ResponsibleEmployeesTest`, `CommissionResponsibilityRegressionTest`, `ApplicantStageContextTest`, `CommissionLifecycleTest` and the updated contract/applicant regressions pass in the 83-test focused run.

## Files changed and why
- `database/migrations/2026_11_07_000007_retire_test_offer_and_academy_stages.php` — retires test-offer/academy stages without deleting rows, histories or commission evidence.
- `app/Http/Controllers/ContractController.php`, `ApplicantController.php`, `ApplicantContractStageController.php`, `ApplicantStageController.php` — case-free contract generation, plural responsibility and stage refusals.
- `app/Models/Applicant.php`, `ApplicantContract.php`, `ApplicantStage.php`, `app/Services/ApplicantStageContext.php`, `ContractTaskService.php`, `EducationalWorkflowNotificationService.php`, `CommissionService.php`, `Contracts/PlaceholderRegistry.php`, `Contracts/PlaceholderResolver.php` — plural assignment, notification, document and commission boundaries.
- `resources/ts/pages/service-contracts.vue`, `applicant-stages.vue`, `applicants.vue`, `all_customers.vue`, `applicant-pipeline.vue`, `resources/ts/api/contracts.ts`, `applicants.ts`, `applicantStages.ts` — remove case-free stage UI and render plural responsible employees.
- `tests/Feature/ContractCaseBoundaryTest.php`, `ResponsibleEmployeesTest.php`, `CommissionResponsibilityRegressionTest.php`, `ApplicantStageContextTest.php` and the related notification/contract regressions — cover the delivered behavior.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-09 | Focused Academy/Task-046/Task-047 suites | isolated testing DB | PASS | terminal | 83 tests / 953 assertions. |
| 2026-11-09 | `npm run typecheck` | working tree | PASS — exit 0 | terminal | |
| 2026-11-09 | `npm run i18n:check` | working tree | FAIL — exit 1 | terminal | Pre-existing unrelated keys only; no Task-046 key reported. |

## Decisions and deviations
- "No case" is defined as no stage-bearing workflow record and no stage history/pipeline card. The existing `applicants` party row remains because contracts, documents and payments depend on it; a no-party interpretation is a stop condition, not this task.
- `responsible_employee_id` remains the counselor/sales owner. The new plural pivot is the former admission assignment and does not enter commission calculations.
- The old `admission_employee_id` column is preserved for rollback but is no longer an active API field; the pivot is backfilled once.
- Stage rows are retired inactive rather than deleted so history and commission evidence survive.
- The permission rename is owned by Task-038; the contract-generation field and pivot are owned by Task-037. This task consumes both and never edits their files.
- The case-free contract-generation boundary is delivered by Task-037; this task retires the catalogue/editor/pipeline surface and verifies the integration.
- Forward ownership of `resources/ts/pages/applicant-case.vue` moved to Task-047 on 2026-11-07 so the relationship-popup work has one case-page writer; this task still delivers the plural API and type contract.

## Blockers and open questions
- No code blocker. Product confirmation is still needed if "no case" means no `applicants` row at all; that needs a separate counterparty model and cannot be done by nulling stages.
- Product confirmation on the post-retirement test-offer/academy commission policy (no new accrual, existing accruals untouched) remains open; the delivered behavior follows that assumption.
- Manual viewport/RTL and native Dari/Pashto review of the changed surfaces remains unrun.

## Handoff notes
- Delivered surfaces: stage retirement, plural responsibility, notifications/task assignment, document placeholders and commission preservation.
- Preserve Task-037 contract generation/term/capacity, Task-042 document dates, Task-035 audit rail and Task-016 seller-only commission rules.
- `applicant-case.vue` remains Task-047-owned; this task's plural API and types are consumed there.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the case-free contract and plural responsibility request.
- `2026-11-07` Boundary correction: Task-037 retains contract generation, the pivot migration and the contract-level permission because it owns term/capacity; this task consumes them and owns the stage/UI/notification/commission surfaces.
- `2026-11-07` Forward ownership of `resources/ts/pages/applicant-case.vue` moved to Task-047 for the university/country relationship and contract-aware case task; this task keeps the plural API/type contract.
- `2026-11-09` Merged Task-047 into `feature/academy-revamp`, verified the case-free/plural-responsibility behavior in the focused 83-test run, and moved the task to Review.
