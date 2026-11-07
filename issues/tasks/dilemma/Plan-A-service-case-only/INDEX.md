# Plan A Task Index — service case only

- **Plan source/version:** User request 2026-11-05, described in `Task-030-service-case-only/Task.md`.
- **Status:** `Alternative — not part of the parallel implementation set`
- **Validation command:** `python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-A-service-case-only`
- **Last validation result:** `PASS (2026-11-05), 1 task, 0 errors` (validated as an isolated alternative)

## Tasks

| Task ID | Folder | Outcome | Status | Dependencies | Exclusive owner / file area | Validation status |
|---|---|---|---|---|---|---|
| Task-030 | `Task-030-service-case-only/` | One contracted service line is the only case; Applications lists one row per service, no case is created for an applicant, and the service case owns the wrapper's working capabilities | Ready | None | `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantContractStageController.php`, `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/VisitorController.php`, `app/Http/Controllers/EducationalDashboardController.php`, `app/Http/Controllers/EducationalReportController.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`, `app/Http/Resources/ApplicantContractResource.php`, `app/Http/Resources/ApplicantResource.php`, `app/Models/Applicant.php`, `app/Models/ApplicantContract.php`, `resources/ts/pages/applicants.vue`, `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/service-case.vue`, `resources/ts/pages/service-contracts.vue`, `resources/ts/api/applicants.ts`, `resources/ts/api/serviceCases.ts`, `resources/ts/api/task.ts`, `resources/ts/api/kanban.ts`, `resources/ts/components/income/ServicePaymentsDialog.vue`, `resources/ts/pages/income.vue`, `resources/ts/plugins/1.router/routes.ts`, `routes/api.php`, `resources/ts/plugins/i18n/locales/en.json`, `resources/ts/plugins/i18n/locales/fa-AF.json`, `resources/ts/plugins/i18n/locales/ps-AF.json`, `tests/Feature/ServiceCaseOnlyTest.php`, `tests/Feature/ServiceCaseIsolationTest.php`, `tests/Feature/VisaCaseWorkspaceTest.php`, `tests/Feature/ApplicantPipelineTest.php`, `tests/Feature/ApplicantContractStageTest.php`, `tests/Feature/ApplicantTaskIntegrationTest.php`, `tests/Feature/BuiltInContractGenerationTest.php` | Passed |
