# Task Task-046 — Make test and academy contracts case-free and case responsibility plural

## Metadata
- Task ID: `Task-046`
- Title: `Make test and academy contracts case-free and case responsibility plural`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with contract, RBAC and commission review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-07: test-offer and academy contracts must not create a case or a stage, their stages and the stage-creation path must be removed, the terminal-stage locked label must be removed from the frontend, admission employee must become plural responsible employees end to end, the admission permission must be transformed, commissions must keep working, and contract creation must keep a dedicated permission for choosing the case's responsible employees; repository inspection 2026-11-07`
- Requirement / behavior IDs: `CASE-FREE-001`, `CASE-FREE-002`, `CASE-FREE-003`, `CASE-FREE-004`, `RESP-EMP-001`, `RESP-EMP-002`, `RESP-EMP-003`, `COMMISSION-REG-001`

## Objective
A test-offer or academy contract is a commercial document, not a stage-driven case: creating it still records the party, contract, service line and money, but it creates no stage, stage history or pipeline card. The stage catalogue becomes an admission-only workflow for scholarship and private cases; existing test-offer and academy stage rows and history are retired without deletion. On the contracts page, the singular admission-employee field becomes a plural responsible-employees assignment shared with the case forms, the permission that gates choosing it is renamed in place, and the commission ledger continues to use only its existing seller fields so no commission amount or settlement changes.

## Context and problem
`App\Services\Contracts\ContractGenerationService::createApplicant()` always selects a `new` stage, writes `applicants.current_stage_id`, creates an `ApplicantStageHistory` row, and `create()` repeats that for the `applicant_contract` line. `ApplicantStageContext` also treats test-offer and academy as stage workflows, and `ApplicantStageController` accepts stages for all four built-in services. The stage editor renders test scope and a locked chip for terminal rows. The client has now stated that test-offer and academy are not cases and do not need stages.

The second problem is naming and cardinality. `applicants.admission_employee_id` stores one admission employee, `assign_admission_employee` gates it, and `ContractController::serviceOptions()` exposes singular `admission_employees` only when that permission is held. The contract form, applicant forms, task assignment, notifications and the admission placeholder all read the same singular field. The client wants multiple responsible employees on one case and the label changed end to end.

Commissions are earned by a completed case and calculated from `contracts.sold_by_employee_id`, `applicant_contract.sold_by_employee_id` and `contracts.employee_id`; the admission employee is not a commission recipient. That existing source of truth must not change. Test-offer and academy records that already have commission accruals must not have them voided merely because their stage workflow is retired.

## Scope
### Included
- Add a migration that retires test-offer and academy stages: set those stage rows inactive, clear `applicants.current_stage_id` and `applicant_contract.current_stage_id` for those services, and preserve every stage row, stage-history row, contract, line, income and commission row. The migration must be idempotent and must not delete data.
- Consume the additive `applicant_responsible_employees` pivot and one-time legacy backfill delivered by Task-037; keep the legacy column in place for rollback and history.
- Consume Task-037's contract-generation boundary for `test_offer` and `academy`: the party record and contract line have `current_stage_id = null`, no `ApplicantStageHistory` row is written, and no `ApplicantState`/line stage is required. The contract, line, fee, payment, academy enrollment, task and document behaviour remains otherwise unchanged. This task does not edit `ContractGenerationService` or `StoreServiceContractRequest`.
- Preserve scholarship and private case creation, stages, terminal reasons, stage history, pipeline and stage-based task completion exactly as they are.
- Change `ApplicantStageContext`, `ApplicantStageController`, `ApplicantStage` and `ApplicantContractStageController` so test-offer and academy have no stage applicability and no legal stage write; stage create/update for those services returns the specific business refusal and writes nothing. The stage index/editor must not expose those services.
- Change the stage editor frontend so only scholarship and private are selectable, the test-scope controls and column are removed, and the terminal-stage locked chip/label is removed. Keep the server lock, the hidden edit/delete controls for locked rows and the 409 refusal unchanged.
- Change `ApplicantController::pipeline()` and the pipeline page so test-offer and academy lines produce no stage pipeline cards; the contract registers remain their operational surface. The applications register may still show the party as a contact record, but not as a staged case.
- Rename the singular admission assignment to plural responsible employees end to end: request payload `responsible_employee_ids`, response `responsible_employee_ids` and `responsible_employees`, pivot relation and sync, validation, contract options, resources, contract generation, applicant create/update, task assignment and notifications.
- Rename `assign_admission_employee` to `assign_responsible_employees` in place rather than adding a second live permission. Task-038 owns the permission seeder and migration; this task consumes the new name and verifies that every role that held the old permission still holds the new one.
- Require `assign_responsible_employees` to choose or change responsible employees on contract creation and contract/case editing. Without it, the options are not returned, a submitted list or change is refused with 403, and no pivot row is written. The UI must hide or disable the control with an explanatory accessible label rather than relying on a late 403.
- Keep `responsible_employee_id` as the counselor/sales-owner field. It is not renamed by this task; its scope, dashboards, notices and commission behaviour remain unchanged.
- Update the contract and applicant forms: `service-contracts.vue`, `applicants.vue` and `all_customers.vue` use a multi-select, the plural label and the new permission. The service-contract form sends the array only for scholarship/private, where a case exists; test-offer and academy contracts do not carry responsible employees. `applicant-case.vue` is forward-owned by Task-047, which applies the same plural control to the case edit form; this task supplies the API and type contract.
- Update `ContractResource` and `ApplicantResource` to return the plural relation and IDs. Keep the legacy database column out of the active response; do not return a singular `admission_employee*` field.
- Update `ContractTaskService` and `EducationalWorkflowNotificationService` so every responsible employee is assigned to the delivery task and notified once, in addition to the counselor; deduplicate users and keep the actor fallback. The contract-generated notification goes to all responsible employees, not one admission employee.
- Update `PlaceholderRegistry` and `PlaceholderResolver` so the document label is plural responsible employees and `applicant.responsible_employees` resolves the joined names. Preserve the old placeholder key as a read-only compatibility alias returning the same joined names; do not emit a singular admission label.
- Update `CommissionService` so test-offer and academy applicants are skipped without creating or voiding accruals. Scholarship and private commission calculations remain byte-for-byte on their existing seller fields; responsible employees never enter the accrual key, percentage, base or amount.
- Update the demo seeders that still set `admission_employee_id` so the pivot is populated for seeded cases, while the legacy column may remain for historical compatibility.
- Update the existing tests and add focused suites for the case/stage boundary, the plural assignment/permission, and the commission regression.

### Excluded
- Deleting test-offer or academy stage rows, stage histories, applicants, contracts, lines, incomes, payments, commission accruals or settled money.
- Repurposing `applicants` as a customer entity, dropping `admission_employee_id`, or creating a second customer table. A dedicated Academy `students` profile linked to an applicant is allowed (Task-036); it is not a replacement customer entity. If the client literally means no `applicants` row at all, this task stops: a contract needs a counterparty and service detail record, and that requires a separate architecture decision.
- Building a new test-offer or academy case workspace, stage replacement, pipeline, WYSIWYG editor, certificate or public verification feature.
- Changing scholarship/private stage keys, terminal stages, terminal-reason rules, document gate, stage scope, case isolation or line-stage writer.
- Changing contract fees, discounts, currency, payments, academy term/capacity, enrollment status, slip/contract templates or print layouts.
- Renaming `responsible_employee_id` (counselor), changing its scope/dashboards, or using responsible employees for commission, payroll, bonuses or sales attribution.
- Adding a new route or navigation entry; this task works through existing contract, applicant, stage and pipeline endpoints.
- Changing locale files, permission seeders/routes or other tasks' owned files from inside this task. Those are dependencies, not shared ownership.

## Preconditions and assumptions
- Task-038 lands the `assign_responsible_employees` permission rename, its role-grant preservation and its migration; Task-043 lands the frozen locale keys listed below. This task does not edit those files.
- Task-037's academy term/capacity, case-free contract generation, pivot migration and contract-level responsible-employee validation/permission are already in place and must stay intact; this task does not edit those files. Task-042's contract/slip term fields and placeholder registry are already in place.
- Task-028's line-as-case model and Task-029's stage context/terminal rules are already in place. This task changes only the test-offer/academy applicability and the stage editor presentation.
- The existing `applicants.current_stage_id` and `applicant_contract.current_stage_id` columns are nullable and their foreign keys allow null. Verify before writing the migration; if not, the migration must alter nullability first.
- "No case" means no stage-bearing workflow record and no stage history/pipeline card. The `applicants` row remains the party/counterparty for the contract, document placeholders, payments and history. It is not a case until a scholarship/private line or an explicit case workflow exists.
- Responsible employees are chosen from the existing active admission-assignment scope (`AgencyStaffAssignment::query('admission')`). The task does not broaden eligibility to every employee; if the client wants all active employees, that is a separate decision.
- The legacy `admission_employee_id` column is preserved but no longer read or written by the active API. Existing rows are backfilled once; a second migration run creates no duplicate pivot rows.
- Commission behavior is preserved, not redesigned: scholarship/private completed cases still accrue from the existing seller fields; test-offer/academy accruals are not created or voided by this task.
- No production/working database is mutated during verification; the migration is rehearsed on a disposable copy.

## Dependencies
- `Task-003` — delivers the contract register, `ContractController`, `ContractResource` and typed contract client this task extends.
- `Task-016` — delivers the commission service and lifecycle suite whose seller-only rule must remain intact.
- `Task-028` — delivers the line-as-case pipeline, applicant pages and stage writer this task narrows to scholarship/private.
- `Task-029` — delivers the stage context, service-scoped stage catalogue, terminal lock and stage editor this task retires for test-offer/academy.
- `Task-035` — delivers the service-contract page and audit rail this task extends with the plural field.
- `Task-037` — delivers academy term/capacity, the case-free contract-generation boundary, the pivot migration and the contract-level plural field/permission; this task consumes those and removes the remaining stage surface.
- `Task-038` — delivers the in-place `assign_responsible_employees` permission rename, role-grant preservation and its migration.
- `Task-042` — delivers the placeholder registry/resolver and contract/slip term fields this task extends with the plural responsible-employee placeholder.
- `Task-043` — delivers the frozen responsible-employee labels and the stage-service refusal sentence in all three locales/catalogs.

## Contracts and boundaries
- Case-free contract shape: test-offer/academy creation returns `contract`, `applicant`, `applicant_contract` and money exactly as before, but `applicant.current_stage_id` and `line.current_stage_id` are `null`, `stage_history` is empty and no `applicant_stage_histories` row is inserted. End dates, capacity, fees, payments and academy enrollment behavior are unchanged.
- Stage catalogue: active stages exist only for scholarship and private. A create, update or delete attempt for a test-offer/academy stage returns 422 `business.applicant_stage_service_not_supported`; no row is written or removed. A stage-scoped read for those services returns an empty list, not another service's stages.
- Stage editor: the service select offers scholarship and private only; the test-scope controls/column and the locked chip are gone. A locked terminal row still has no edit/delete control and the API still returns 409 `business.applicant_stage_locked` if called directly.
- Pipeline: `GET /applicants-pipeline` returns no card for a test-offer/academy line; the pipeline page's service selector offers only scholarship and private. The contract register remains the test-offer/academy operational list. The applications register may show the party but no stage control.
- Responsible employees payload: `responsible_employee_ids` is an array of distinct positive active employee ids from the admission assignment scope. The response returns the same ids and `responsible_employees: [{id, name}]`. An empty array clears all assignments. The field is accepted only for scholarship and private; it is prohibited for test-offer/academy.
- Permission: `assign_responsible_employees` is the only live gate for choosing or changing the field. `GET /contracts/{service}/options` returns `responsible_employees` only when the caller holds it; otherwise it returns an empty array. A create/update that includes the key without the permission is 403 before any pivot or contract write. The same rule applies to applicant create/update.
- Legacy field: `admission_employee_id` is not accepted by the new API, is not returned as a singular field, and is not written. The column and its historical values remain for rollback; the backfill copies them once into the pivot. The old permission name is not checked by new code.
- Notifications/tasks: every responsible employee with a user account receives one contract-generated notice and is assigned to the line's delivery task; the counselor remains assigned as before; duplicates are collapsed; the actor is the fallback only when both lists are empty. No notification is sent to a removed employee.
- Placeholders: `applicant.responsible_employees` resolves the joined names of all assigned employees (or the existing unassigned label), and the old `applicant.admission_employee` alias resolves the same joined names for existing templates. The visible registry label is `Responsible Employees`; no singular admission label remains.
- Commission: the accrual key and amount remain `contract_id` + `applicant_contract_id` + `employee_id` derived from the existing seller/employee fields. Responsible employees are never inserted, and test-offer/academy applicants return early without changing existing accruals. Settled accruals remain immutable.
- Compatibility: existing scholarship/private contracts, stage histories, task completion, income receipts, academy slips and contract print snapshots are unchanged except for the plural staff display. No route, nav, locale or seeder is edited by this task; those belong to Tasks 038 and 043.
- Error behavior: refusal writes nothing; a 403 on the permission leaves the contract, line, pivot and notifications unchanged. Validation errors name `responsible_employee_ids` or the specific field.
- Files this task must not modify: `resources/ts/pages/applicant-case.vue`, `resources/ts/plugins/i18n/locales/**`, `lang/**`, `database/seeders/PermissionSeeder.php`, `database/seeders/RolePermissionSeeder.php`, `routes/api.php`, `routes/academy/**`, `resources/ts/plugins/1.router/routes.ts`, `resources/ts/navigation/**`, academy pages/components, contract/slip/certificate print templates, `App\Services\AcademySlipService`, the academy enrollment/term service, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`, and `database/migrations/2026_11_07_000008_create_applicant_responsible_employees.php`.

## Exclusive file ownership
- `database/migrations/2026_11_07_000007_retire_test_offer_and_academy_stages.php` — idempotent stage retirement and stage-reference clearing; no deletes.
- `app/Http/Controllers/ContractController.php` — responsible-employee options and contract responses; preserve the register.
- `app/Http/Resources/ContractResource.php` — plural responsible-employee payload; no active singular field.
- `app/Http/Controllers/ApplicantController.php` — plural validation/sync, permission, notifications and case-free pipeline filtering.
- `app/Http/Controllers/ApplicantContractStageController.php` — refuse stage reads/writes for test-offer/academy; preserve scholarship/private writer.
- `app/Http/Controllers/ApplicantStageController.php` — admission-only stage catalogue; specific refusal for retired services; remove test scope.
- `app/Models/Applicant.php` — responsible-employees pivot relation, nullable stage behavior and case-bearing helpers.
- `app/Models/ApplicantContract.php` — nullable stage guard for case-free services.
- `app/Models/ApplicantStage.php` — supported-service contract and retired-stage semantics.
- `app/Services/ApplicantStageContext.php` — no stage applicability for test-offer/academy.
- `app/Http/Resources/ApplicantResource.php` — plural responsible-employee payload.
- `app/Services/ContractTaskService.php` — assign all responsible employees plus counselor; skip stage sync for case-free lines.
- `app/Services/EducationalWorkflowNotificationService.php` — plural responsible-employee notices and compatibility rename.
- `app/Services/CommissionService.php` — skip case-free services without voiding; keep seller-only calculation.
- `app/Services/Contracts/PlaceholderRegistry.php` — plural label and compatibility alias.
- `app/Services/Contracts/PlaceholderResolver.php` — plural joined-name resolution and legacy alias.
- `resources/ts/pages/service-contracts.vue` — multi-select, plural label, permission and payload.
- `resources/ts/pages/applicant-stages.vue` — admission-only service options, remove test scope and locked chip.
- `resources/ts/pages/applicants.vue` — plural create/edit field and no admission singular label.
- `resources/ts/pages/all_customers.vue` — plural responsible-employee field.
- `resources/ts/pages/applicant-pipeline.vue` — admission-only pipeline services.
- `resources/ts/api/contracts.ts` — plural field and option types; remove singular admission field.
- `resources/ts/api/applicants.ts` — plural applicant types and payload.
- `resources/ts/api/applicantStages.ts` — admission-only service type; remove test scope.
- `database/seeders/ApplicantSeeder.php` — seed the pivot for the demo cases.
- `database/seeders/NotificationDemoSeeder.php` — seed the pivot for the notification demo.
- `tests/Concerns/CreatesBuiltInContracts.php` — shared contract fixtures with plural responsibility.
- `tests/Feature/ContractCaseBoundaryTest.php` — new no-case/no-stage and retirement coverage.
- `tests/Feature/ResponsibleEmployeesTest.php` — new plural assignment, permission, backfill and notification coverage.
- `tests/Feature/CommissionResponsibilityRegressionTest.php` — new seller-only and no-void regression coverage.
- `tests/Feature/BuiltInContractGenerationTest.php` — update contract generation expectations.
- `tests/Feature/RealtimeNotificationTest.php` — update plural responsible-employee notification expectations.
- `tests/Feature/VisaFirmComplianceTest.php` — update permission-name and assignment expectations.
- `tests/Feature/AgencyWorkflowCoherenceTest.php` — update applicant responsibility expectations.
- `tests/Feature/NotificationDemoSeederTest.php` — assert pivot seeding instead of only the legacy field.
- `tests/Feature/EducationalWorkflowIntegrationTest.php` — update test-offer/academy creation and staff expectations.
- `tests/Feature/ApplicantStageCatalogTest.php` — admission-only catalogue and refusal coverage.
- `tests/Feature/ApplicantStageScopeTest.php` — remove retired test/academy scope expectations.
- `tests/Feature/ApplicantStageContextTest.php` — no stage context for test-offer/academy.
- `tests/Feature/ApplicantContractStageTest.php` — no stage write for case-free services.
- `tests/Feature/CommissionLifecycleTest.php` — preserve existing lifecycle and add the case-free guard.
- `tests/Feature/ApplicantPipelineTest.php` — no test-offer/academy cards.
- `tests/Feature/ServiceCaseIsolationTest.php` — update case-free line expectations.
- `tests/Feature/ApplicantWorkflowGateTest.php` — update stage expectations for the narrowed services.
- `tests/Feature/VisaCaseWorkspaceTest.php` — update the no-stage workspace expectations.
- `tests/Feature/ApplicantTaskIntegrationTest.php` — update task assignee expectations for plural responsibility.

## Acceptance criteria
- [ ] Given a new test-offer or academy contract, when it is created successfully, then the party, contract and service line exist, `applicant.current_stage_id` and `line.current_stage_id` are null, no `applicant_stage_histories` row is written, and no stage lookup is required.
- [ ] Given an existing test-offer or academy applicant or line with a stage, when the retirement migration runs twice, then those stage references are null, the stage rows are inactive rather than deleted, stage histories/contracts/lines/incomes/commission rows are preserved, and the second run makes no further change.
- [ ] Given a stage create, update or delete request for test-offer or academy, when it is submitted, then it is refused with `business.applicant_stage_service_not_supported` or the existing in-use/lock refusal, no stage row is written or removed, and the stage list for those services is empty.
- [ ] Given the stage editor, when it renders, then only scholarship and private are selectable, the test-scope controls/column are absent, the locked chip/label is absent for terminal rows, and every locked row still has no edit/delete control.
- [ ] Given the application pipeline, when it is queried or rendered, then no test-offer or academy line appears as a stage card, while scholarship/private cards and moves behave exactly as before; the contract registers still list the case-free contracts.
- [ ] Given a scholarship or private contract created or edited with two responsible employees, when it succeeds, then both pivot rows exist, the response returns both ids and names, and clearing the array removes every pivot row without changing the counselor field.
- [ ] Given a caller without `assign_responsible_employees`, when they submit or change `responsible_employee_ids`, then the request is 403, no pivot/contract/notification write occurs, and the options endpoint returns no responsible-employee list; a caller with the permission can choose multiple eligible employees.
- [ ] Given existing applicants with a non-null legacy admission employee, when the pivot migration runs, then each employee is backfilled exactly once, a second run creates no duplicate, the legacy column is unchanged, and the new API returns only the plural fields.
- [ ] Given a contract with two responsible employees and a counselor, when the delivery task and contract notice are created, then each responsible employee's user is assigned/notified once, the counselor remains assigned, duplicates are collapsed, and no removed employee receives a notice.
- [ ] Given a completed scholarship/private case with multiple responsible employees, when commission sync runs, then accruals are still keyed and calculated only from the existing seller fields and their amounts are unchanged; responsible employees never appear as commission recipients.
- [ ] Given a test-offer or academy case with existing commission accruals, when commission sync runs after stage retirement, then no new accrual is created, no existing accrual is voided or mutated, and settled money remains untouched.
- [ ] Given the contract document placeholders, when `applicant.responsible_employees` is resolved, then it renders the joined names of every assigned responsible employee, the legacy `applicant.admission_employee` alias resolves the same names, and the registry label is plural.
- [ ] Given the six locale/catalog files delivered by Task-043, when the changed pages and refusal path render in English, Dari and Pashto, then `serviceContracts.responsibleEmployees`, `serviceContracts.responsibleEmployeesOptional`, `serviceContracts.responsibleEmployeesHint`, `serviceContracts.placeholders.responsibleEmployees`, `agencyStaff.responsibleEmployees`, `educational.responsibleEmployees`, `common.fields.responsibleEmployees` and `api.errors.business.applicant_stage_service_not_supported` exist with non-empty matching values and no raw key.
- [ ] Given the focused suites, when `php artisan test --filter=ContractCaseBoundaryTest`, `--filter=ResponsibleEmployeesTest`, `--filter=CommissionResponsibilityRegressionTest`, `--filter=ApplicantStageContextTest`, `--filter=CommissionLifecycleTest` and the updated contract/applicant regressions run, then they pass on an isolated database; `npm run typecheck` and `npm run i18n:check` exit 0.

## Verification plan
- Automated: run the new and updated feature suites on an isolated SQLite/MySQL test database; assert stage refs, absence of history rows, inactive stage rows, preserved counts, pivot cardinality, permission 403/200, notification users, task assignees and commission amounts. Run `php artisan test --filter=ContractCaseBoundaryTest`, `--filter=ResponsibleEmployeesTest`, `--filter=CommissionResponsibilityRegressionTest`, `--filter=ApplicantStageCatalogTest`, `--filter=ApplicantStageScopeTest`, `--filter=ApplicantStageContextTest`, `--filter=CommissionLifecycleTest`, `--filter=BuiltInContractGenerationTest`, `--filter=EducationalWorkflowIntegrationTest` and the updated applicant/pipeline suites.
- Migration rehearsal: on a disposable copy, record counts of `applicants`, `applicant_contract`, `applicant_stages`, `applicant_stage_histories`, `incomes`, `commission_accruals` before and after; assert only null stage refs and inactive stage flags changed; run the migration twice.
- Permission matrix: roles with and without `assign_responsible_employees`; create/edit scholarship and private contracts with zero, one and two responsible employees; assert options visibility, 403, no partial writes and role-grant preservation from the Task-038 migration.
- Commission regression: complete a scholarship case with one and two responsible employees plus a commission-based seller; assert one accrual per eligible seller/line and unchanged amount. Seed a test-offer/academy accrual and assert sync leaves it untouched.
- Frontend: `npm run typecheck`, `npm run i18n:check`, and a browser pass on `/contracts/scholarship`, `/contracts/test-offer`, `/contracts/academy`, `/applicants`, an applicant case and `/applicant-stages` at 390/768/1280 px in LTR/RTL and light/dark; verify multi-select labels, no locked chip, no test/academy service option and no stage control for case-free contracts.
- Print/regression: run the existing academy slip/contract and scholarship/private contract document checks; confirm the plural placeholder does not change layout or page size.
- Evidence: migration before/after counts, test output, permission matrix, commission fixture values, browser notes and any limitation.

## Implementation notes
Treat `current_stage_id` as the case-bearing marker. Do not add a second `is_case` column unless the implementation proves the marker ambiguous; if it does, stop and record the schema decision rather than silently adding a parallel flag.

For the stage retirement, use the query builder so model guards cannot block the migration. Set `is_active = false` for the two services, clear the two stage references, and preserve all rows. Do not call `ApplicantStage::delete()` or `Applicant::delete()`. The `ApplicantStageContext` should return `false`/an empty query for those services, and the controller should use one shared constant/helper for the allowed services (`scholarship`, `private`) rather than scattering string checks.

Task-037 owns the pivot migration and the contract-generation sync. Here, add the `belongsToMany` relation on `Applicant` with the pivot table name and `withTimestamps()`, use it for reads and applicant updates, deduplicate and sort ids, and never write the legacy column again. Do not edit the migration or the contract-generation request/service.

For the permission rename, do not add a second gate. Task-038 renames the permission row and preserves role pivots; this task checks the new name everywhere and leaves no code path reading the old name. A caller who had the old permission must still see the options after migration.

For commissions, add the earliest safe guard in `CommissionService` for test-offer/academy and return before any create/void query. Do not alter `lineAccruals()`, its employee ids or its percentage math. The regression test should assert the same accrual count/amount with one versus two responsible employees.

For documents, add the plural placeholder beside the old one, resolve both through the same joined-name helper, and update only the registry label. Do not change template content or print CSS.

For seeders, keep the legacy column write only where a historical fixture needs it; populate the pivot as the active assignment. The demo UI should show responsible employees from the pivot, not from the legacy column.

## Risks and rollback
- Risk: the stage retirement makes existing test-offer/academy history unreadable or removes the stage name from a timeline. Mitigation: soft/inactive retirement, no deletes, and history rendering falls back to the stored stage label only if needed; verify existing history in the migration test.
- Risk: no-case rows are mistaken for scholarship cases and appear in the pipeline or stage editors. Mitigation: one allowed-service helper, explicit null-stage filters and pipeline tests.
- Risk: the permission rename strands a role. Mitigation: in-place rename plus role-pivot preservation and a permission matrix test; no second permission name.
- Risk: plural assignments accidentally change commission recipients. Mitigation: no commission write from the pivot, seller-only regression fixture and a two-responsible-employee assertion.
- Risk: legacy `admission_employee_id` data is lost or duplicated. Mitigation: keep the column, idempotent backfill, unique pair and a rerun test.
- Risk: the UI sends an array to the old singular key. Mitigation: update the typed client and search the repository for `admission_employee_id`/`admissionEmployee` before handoff; zero live producers.
- Rollback: restore the pre-task code and reverse the additive pivot migration; stage rows remain inactive and references can be restored from the preserved history/snapshot if needed. Do not drop the legacy column, delete history or reverse settled commissions.
- Stop condition: if the product owner means no `applicants` row at all, stop before migration and plan a separate counterparty model; do not delete the party that documents, payments and contracts depend on.

## Handoff checklist
- [ ] Exclusive ownership and the forward handoffs from Tasks 003, 016, 028, 029, 035 and 042 are respected; Task-037 is a dependency, not a forward handoff.
- [ ] `resources/ts/pages/applicant-case.vue` is forward-owned by Task-047; its plural control is not reimplemented or blocked here.
- [ ] Task-038 permission rename and Task-043 frozen locale keys are present before UI verification.
- [ ] No test/academy stage is created; existing stage rows/history are preserved.
- [ ] No new permission name is added alongside the old one; role grants survive the rename.
- [ ] Commission amounts/settlements and settled test/academy accruals are unchanged.
- [ ] Every acceptance criterion is supported by recorded evidence in `Progress.md`.
- [ ] Remaining policy questions and not-run checks are explicit.
